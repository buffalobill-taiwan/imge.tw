$().ready(function(){
	initTab();
	initHome();
	initUpload();
});

var IMGE_TASK = [];
var CURRENT_TASK_CARD = null;
var UPLOAD_RUNNING = false;
var RECENT_LOAD = false;
var TOP_LOAD = false;
var ALL_LOAD = false;
var ALL_BEFORE = null;

function initPaste(){
	$("#paste").on("paste", pasteImage);
}

function pasteImage(event){
	const clipboardItems = event.originalEvent.clipboardData.items;
    const items = [].slice.call(clipboardItems).filter(function (item) {
        // Filter the image items only
        return item.type.indexOf('image') !== -1;
    });
    if (items.length === 0) {
        return;
    }
    const item = items[0];
    const blob = item.getAsFile();

	if(!blob){return;}
	IMGE_TASK.push(blob);
	startImgeTask();
}

function initUpload(){
	var fromTW = $("input[name='fromTW']").val();
	if(fromTW){
		initDropFile();
		initBrowseFile();
		initPaste();
	}else{
		var reason = "You can't upload images, your IP is not allowed.";
		$("#browse-file").addClass("disabled").attr("title", reason);
		$("#drop_zone").addClass("disabled").attr("title", reason);
		$("#paste_zone").addClass("disabled").attr("title", reason);
		$("#paste").addClass("disabled").prop("disabled", true).attr("title", reason);
	}
}

function startImgeTask(){
	switchTab("#tab-task");
	if(!UPLOAD_RUNNING){
		UPLOAD_RUNNING = true;
		handleImgeTask();
	}
}

function handleImgeTask(){
	if(IMGE_TASK.length == 0){
		UPLOAD_RUNNING = false;
		$("#task-count").text("");
		unsetRecentLoad();
		return;
	}
	$("#task-count").text("("+IMGE_TASK.length+")");
	var file = IMGE_TASK.shift();
	var invalid = invalidFile(file);
	CURRENT_TASK_CARD = createCardFromFile(file, invalid);
	if(!invalid){
		uploadFile1GetTicket(file);
	}else{
		setTimeout(handleImgeTask, 1000);
	}
}

function failUploadTask(message){
	failCard(message);
	setTimeout(handleImgeTask, 1000);
}

function uploadFile1GetTicket(file){
	$.ajax({url: "/ticket", dataType: "json", timeout: 15000})
		.done(function(data){
			if(!data || !data.ticket){
				failUploadTask((data && data.error) || "Unable to get upload ticket");
				return;
			}
			uploadFile2upload(file, data.ticket);
		})
		.fail(function(){ failUploadTask("Unable to get upload ticket"); });
}

function uploadFile2upload(file, ticket){
	var fd = new FormData();
	fd.append("file", file);
	$.ajax({
		url: "/upload/" + encodeURIComponent(ticket),
		type: 'POST',
		data: fd,
		dataType: 'json',
		timeout: 60000,
		processData: false,
		contentType: false
	}).done(function(data){
		if(!data || data.error || typeof data.url !== 'string' || !data.url){
			failUploadTask((data && data.error) || "Invalid upload response");
			return;
		}
		uploadFile3result(data);
	}).fail(function(){ failUploadTask("Upload request failed"); });
}

function uploadFile3result(data){
	if(data.duplicate){
		duplicateCard();
	}else{
		doneCard();
	}
	CURRENT_TASK_CARD.find(".imge-filename").text(data.url).attr("title", data.url);
	var footer = CURRENT_TASK_CARD.find(".uk-card-footer");
	var linktag = $("<a></a>").addClass("uk-button uk-button-default")
		.attr("uk-icon","link")
		.attr("href", data.url)
		.attr("target", "_blank")
		.appendTo(footer);
	var copytag = $("<a></a>").addClass("uk-button uk-button-default")
		.attr("uk-icon","copy")
		.attr("data-url", data.url)
		.click(clickCopytag)
		.appendTo(footer);
	CURRENT_TASK_CARD.find(".preview").attr("src", data.url);
	setTimeout(handleImgeTask, 1000);
}

function clickCopytag(event){
	event.preventDefault();
	var url = $(this).attr("data-url");
	navigator.clipboard.writeText(url).then(
	  () => {
		/* clipboard successfully set */
		UIkit.notification({
			message: 'URL Copied',
			status: 'primary',
			pos: 'bottom-center',
			timeout: 1000
		});
	  },
	  () => {
		/* clipboard write failed */
		UIkit.notification({
			message: 'URL not Copied',
			status: 'primary',
			pos: 'bottom-center',
			timeout: 1000
		});
	  }
	);
	return false;
}

function duplicateCard(){
	if(!CURRENT_TASK_CARD){return;}
	var body = CURRENT_TASK_CARD.find(".uk-card-body");
	$("<div></div>").addClass("uk-card-badge uk-label uk-label-warning imgebadge").text("duplicated").appendTo(body);
}

function doneCard(){
	if(!CURRENT_TASK_CARD){return;}
	var body = CURRENT_TASK_CARD.find(".uk-card-body");
	$("<div></div>").addClass("uk-card-badge uk-label imgebadge").text("done").appendTo(body);
}

function failCard(e){
	if(!CURRENT_TASK_CARD){return;}
	var body = CURRENT_TASK_CARD.find(".uk-card-body");
	$("<div></div>").addClass("uk-card-badge uk-label uk-label-danger imgebadge").text("fail: "+e).appendTo(body);
}

function invalidFile(file){
	if(file.type && !file.type.match(/^image\//)){
		return "Not Image File";
	}
	if(file.size > 8388608){
		return "File size("+(file.size/1048576).toFixed(2)+"MB) too big.";
	}
	return "";
}

function createCardFromFile(file, invalid){
	var task= $("#task");
	var card = $("<div></div>").addClass("uk-card uk-card-hover uk-card-small").prependTo(task);
	var head = $("<div></div>").addClass("uk-card-header").appendTo(card);
	var header = $("<h3></h3>").addClass("uk-card-title imge-filename uk-text-center").text("Uploading...").appendTo(head);
	var body = $("<div></div>").addClass("uk-card-body").appendTo(card);
	if(invalid){
		body.addClass("uk-card-body uk-card-invalid");
		var p = $("<p></p>").text(invalid).appendTo(body);
		return card;
	}
	var cardimg = $("<img />").addClass("preview").appendTo(body);
	var fr = new FileReader();
	fr.onload = function () {
		cardimg.attr("src", fr.result);
	}
	fr.readAsDataURL(file);

	var footer = $("<div></div>").addClass("uk-card-footer").appendTo(card);
	return card;
}

function initHome(){
	$("#home").click(clickHome);
	$("#reload_recent").click(clickReloadRecent);
	$("#load-more-images").click(loadAllImages);
}

function clickReloadRecent(event){
	event.preventDefault();
	loadRecent1();
	$("html, body").animate({ scrollTop: 0 }, "slow");
	return false;
}

function initTab(){
	$(".nav-tab").click(clickNavTab);
	switchTab("#tab-upload");
}

function clickHome(event){
	event.preventDefault();
	switchTab("#tab-upload");
	return false;
}

function clickNavTab(event){
	event.preventDefault();
	var href = $(this).attr("href");
	switchTab(href);
	return false;
}

function switchTab(href){
	var dom = $(href);
	dom.siblings(".imge-tab").hide();
	dom.show();

	var aTag = $(".nav-tab[href='"+href+"']");
	var li = aTag.closest("li");
	li.siblings("li").removeClass("uk-active");
	li.addClass("uk-active");

	if(href == "#tab-task"){
		$("#li-tab-task").show();
	}
	if(href == "#tab-recent" && !RECENT_LOAD){
		RECENT_LOAD = true;
		setTimeout(unsetRecentLoad, 1000 * 60);
		loadRecent1();
	}
	if(href === "#tab-all" && !ALL_LOAD){
		loadAllImages();
	}
	if(href == "#tab-top" && !TOP_LOAD){
		TOP_LOAD = true;
		//setTimeout(unsetRecentLoad, 1000 * 60);
		//loadTop1();
	}
}

function loadTop1(){
	$.getJSON("/rank",function(data){
		if(data.error){
			UIkit.notification({
				message: 'Error: ' + data.error,
				status: 'primary',
				pos: 'bottom-center',
				timeout: 1000
			});
		}else{
			loadTop2(data);
		}
	});
}

function loadTop2(data){
	var rank = $("#top");
	rank.empty();
	[...data].forEach((item, i) => {
		var card = $("<div></div>").addClass("uk-card uk-card-small uk-card-default uk-card-hover").appendTo(rank);
		var header = $("<div></div>").addClass("uk-card-header").text(" " +item.count).appendTo(card);
		var eye = $("<span></span>").attr("uk-icon", "icon: eye;").prependTo(header);
		var body = $("<div></div>").addClass("uk-card-body").appendTo(card);
		var aTag = $("<a></a>").addClass("uk-inline").attr("href", item.i).attr("data-caption",item.i).appendTo(body);
		var img = $("<img />").attr("src", item.t).attr("width", 128).attr("height", 128).appendTo(aTag);
	});
	$("#reload_recent").show();
}

function unsetRecentLoad(){
	RECENT_LOAD = false;
}

function loadRecent1(){
	$("#reload_recent").hide();
	function showError(){
		UIkit.notification({message: 'Unable to load recent images', status: 'danger'});
	}
	$.ajax({url: "/recent", dataType: "json", timeout: 15000})
		.done(function(data){
			if(Array.isArray(data)){
				loadRecent2(data);
			}else{
				showError();
			}
		})
		.fail(showError)
		.always(function(){ $("#reload_recent").show(); });
}

function loadRecent2(data){
	var slide = $("#slide");
	slide.empty();
	appendGalleryItems(slide, data);
	$("#reload_recent").show();
}

function loadAllImages(){
	var more = $("#load-more-images");
	if(!more.length || more.prop("disabled")){return;}

	more.prop("disabled", true);
	var params = ALL_BEFORE === null ? {} : {before: ALL_BEFORE};
	function showError(){
		UIkit.notification({message: "Unable to load all images", status: "danger"});
	}
	$.ajax({url: "/all", data: params, dataType: "json", timeout: 15000})
		.done(function(data){
			if(!data || !Array.isArray(data.items) ||
				(data.next !== null && !(Number.isInteger(data.next) && data.next > 0))){
				showError();
				return;
			}
			appendGalleryItems($("#all-images"), data.items);
			ALL_LOAD = true;
			ALL_BEFORE = data.next;
			more.toggle(data.next !== null);
		})
		.fail(showError)
		.always(function(){ more.prop("disabled", false); });
}

function appendGalleryItems(slide, data){
	data.forEach(function(item){
		var div = $("<div></div>").appendTo(slide);
		var aTag = $("<a></a>").addClass("uk-inline").attr("href", item.i).attr("data-caption",item.i).appendTo(div);
		var img = $("<img />").attr("src", item.t).attr("width", 128).attr("height", 128).appendTo(aTag);
	});
}

function initBrowseFile(){
	$("#browse-file").click(clickFile);
	$("#browse").on("change",changeBrowse);
}

function changeBrowse(event){
	if(this.files){
		[...this.files].forEach((item, i) => {
			IMGE_TASK.push(item);
		});
		$(this).val("");
	}
	startImgeTask();
}

function clickFile(event){
	event.preventDefault();
	$("#browse").trigger("click");
	return false;
}

function initDropFile(){
	$("#drop_zone").on("dragover", dragoverDropzone);
	$("#drop_zone").on("drop", dropDropzone);
	$("#drop_zone").on("dragover dragenter", inDropzone);
	$("#drop_zone").on("dragleave dragend", outDropzone);
}

function inDropzone(event){
	$(this).addClass("dropped");
}

function outDropzone(event){
	$(this).removeClass("dropped");
}

function dragoverDropzone(event){
	event.preventDefault();
}

function dropDropzone(event) {
	event.preventDefault();
	if (event.originalEvent.dataTransfer.items) {
		[...event.originalEvent.dataTransfer.items].forEach((item, i) => {
			if (item.kind === "file") {
				const file = item.getAsFile();
				if(file){IMGE_TASK.push(file);}
			}
		});
	} else {
		[...event.originalEvent.dataTransfer.files].forEach((file, i) => {
			IMGE_TASK.push(file);
		});
	}
	$(this).removeClass("dropped");
	startImgeTask();
}
