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

使用 IPv4 上傳白名單時，將 `IMGE_UPLOAD_MODE` 設為 `allowlist`，在 `allowip` 增加允許的範圍，並在容器 Nginx 設定可信代理及真實來源 IP。只信任反向代理的實際來源位址，並確認應用程式收到的是訪客 IP。首次初始化附的 loopback 白名單適用於本機來源。內層 Nginx 提供 10 MiB 請求上限，外層代理也要容納 multipart 開銷。

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
