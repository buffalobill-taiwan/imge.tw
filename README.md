# imge.tw

用 PHP、MariaDB 和 Nginx 架設的圖片分享站，支援上傳、縮圖、最近圖片和重複圖片辨識。

## 快速開始

需要 Docker Engine 或 Docker Desktop、Docker Compose v2，以及可執行 `sh` 和 `openssl` 的環境。Windows 可從 WSL 執行。下載或 clone 專案後，在專案根目錄執行：

```sh
sh scripts/setup.sh
```

第一次執行會建立 `.env`，產生兩組資料庫密碼、建置映像並啟動站台。MariaDB 資料卷第一次啟動時會建立資料表。

| 用途 | 預設網址 |
| --- | --- |
| 首頁和上傳 | http://localhost:8780 |
| 原圖 | http://localhost:8781 |
| 縮圖 | http://localhost:8782 |

查看狀態用 `docker compose ps`，查看紀錄用 `docker compose logs -f`。執行 `docker compose down` 可停止站台，資料庫及圖片會保留在 `database` 和 `media` 資料卷中。`docker compose down --volumes` 會刪除這兩個資料卷。

## 設定網址與對外部署

`.env.example` 列出可調整的設定。修改連接埠時，同時更新對應的 `IMGE_*_URL`，再執行 `docker compose up -d`。初次啟動後請保留 `.env`，因為 MariaDB 只在初始化資料卷時用環境變數建立帳號，變更 `.env` 不會旋轉已建立的密碼。

預設對外連接埠只綁定 `127.0.0.1`，且本機起步設定允許上傳。若要公開站台，請由主機的 HTTPS 反向代理分別將主站、原圖、縮圖導到三個連接埠，並用 `IMGE_URL`、`IMGE_IMAGE_URL`、`IMGE_THUMB_URL` 設定完整的 HTTPS 網址。原圖與縮圖仍使用獨立網域。

### 透過反向代理取得訪客 IP

容器內的 Nginx 預設使用 TCP 連線的來源 IP。透過反向代理部署時，在 `deploy/nginx/imge.conf` 加入 `set_real_ip_from`，填入 **容器收到代理連線時看到的來源 IP**。主機代理經過 Docker 轉送時，這個位址可能是 Docker 網路閘道，而非主機的 loopback。只加入實際受控的代理位址，避免信任整個 Docker 子網。如果直連流量也會從同一個位址抵達容器，請先限制直連，確保只有代理能經由該位址連入。

若最外層代理也是 Nginx，且直接面向訪客，請在轉送的 `location` 內覆寫訪客提供的標頭。

```nginx
proxy_set_header X-Forwarded-For $remote_addr;
```

若有多層代理，由入口代理覆寫標頭，後續可信代理再附加各自看到的來源位址。容器 Nginx 從標頭右側略過可信代理，將找到的第一個非可信位址透過 `REMOTE_ADDR` 傳給 PHP。未設定可信代理時，Nginx 會忽略 `X-Forwarded-For`。

### 台灣 IP 上傳限制

本站提供公開瀏覽，台灣 IP 可匿名上傳。若由 PHP 執行限制，將 `IMGE_UPLOAD_MODE` 設為 `allowlist`，並在資料庫的 `allowip` 表加入允許的台灣 IPv4 範圍。首頁、`/ticket` 和 `/upload/<ticket>` 會各自檢查來源 IP。票券有效期為五分鐘，領票與上傳須使用同一個來源 IP。

白名單目前只支援 IPv4，空站初始化只加入 `127.0.0.1`。如果由外層代理執行台灣 IP 限制，應讓 `/ticket` 和 `/upload/<ticket>` 都套用同一套地區規則，並保留公開瀏覽。

### 部署後驗收

1. 加入可信代理前，先透過公開網址開啟首頁，再用 `docker compose logs --tail=30 web` 查看 Nginx 看見的連線來源。確認該位址只供受控代理連入後，才將它填入 `set_real_ip_from`。
2. 修改後執行 `docker compose up -d --build web`，再以 `docker compose exec web nginx -t` 檢查設定。重新從公開網址開啟首頁，確認存取日誌的來源 IP 已是測試端的對外 IP。
3. 分別從允許的台灣 IPv4 和非台灣 IPv4 測試。台灣來源應能領票並完成上傳。非台灣來源應能瀏覽，但領票和上傳都應被拒絕。若由 PHP 執行白名單，拒絕時會回傳含 `error` 的 JSON，驗收時請檢查回應內容。

內層 Nginx 提供 10 MiB 請求上限，外層代理也要容納 multipart 開銷。

## 資料與移機

- `app/` 放 PHP 邏輯和模板，`public/` 放入口和靜態檔，`deploy/` 放服務配置，`database/init/` 放空站建表 SQL。
- 從舊站搬遷時，以同一時間點的資料庫備份和 `storage/i`、`storage/t` 為一組。新服務啟動前，將舊資料匯入專用資料庫並把兩個目錄還原到 `media` 資料卷根目錄，讓 PHP-FPM 的 `www-data` 擁有寫入權限。匯入前備份任何既有的目標資料。
- 如舊站自訂了 Base62 字元順序，把原來的 62 字元依原順序設定在 `IMGE_BASE62`，以保留圖片短碼。移機後抽查舊原圖與縮圖。
- 公開儲存庫只包含程式、空站結構與部署設定。自己的 `.env`、SQL 備份及圖片放在儲存庫之外。專案沒有附上應用程式授權；公開發布前請由權利人決定授權。UIkit 與 jQuery 的授權告示保留在各自的來源檔。

## 驗證

```sh
python3 scripts/smoke.py
```

請在測試站執行。此命令需要 Python 3 和 curl，會建立一張測試圖，檢查上傳、原圖、縮圖與重複上傳。GitHub Actions 會在獨立的 Compose 環境執行相同測試。
