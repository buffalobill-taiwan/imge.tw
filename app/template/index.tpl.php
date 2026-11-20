<?php include("_header.tpl.php");?>
	<?php include("_nav.tpl.php");?>
<div id="main" class="uk-container uk-container-center uk-margin-top uk-margin-large-bottom">
	<input type='hidden' name='fromTW' value="<?=($fromTW)?1:''?>" />
	<input type='hidden' name='isMobile' value="<?=($isMobile)?1:''?>" />
	<div id="tab-upload" class="uk-width-1-1 imge-tab">
		<h2 class="uk-heading-medium">Upload, Share. That's it!</h2>
		<div class="uk-child-width-1-4@m uk-grid-small uk-grid-match" uk-grid >
<?php if(!$isMobile):?>
			<div id="drop_zone" class="uk-card uk-card-default uk-card-body uk-card-hover" >
				<h3 class="uk-card-title uk-text-center"><span uk-icon='icon: move; ratio: 6' /></h3>
				<p class="uk-text-default uk-text-center">Drop Images Here</p>
			</div>
<?php endif;?>
			<div id="browse-file" class="uk-card uk-card-default uk-card-body uk-card-hover">
				<h3 class="uk-card-title uk-text-center"><span uk-icon='icon: image; ratio: 6' /></h3>
				<p class="uk-text-default uk-text-center">Click to Choose Images</p>
			</div>
			<input style="display:none;" type="file" id="browse" name="browse" accept="image/*" multiple>
<?php if(!$isMobile):?>
			<div id="paste_zone" class="uk-card uk-card-default uk-card-body uk-card-hover">
				<textarea id="paste" class="uk-textarea" placeholder="...Or Paste Image Here" readonly></textarea>
			</div>
<?php endif;?>
		</div>
	</div>
	<div id="tab-task" class="uk-width-1-1 imge-tab">
		<h2 class="uk-heading-medium">Your Upload Task<span id='task-count'></span></h2>
		<div id="task" class="uk-child-width-1-4@m uk-grid-small uk-grid-match" uk-grid >
		</div>
	</div>
	<div id="tab-recent" class="uk-width-1-1 imge-tab">
		<h2 class="uk-heading-medium">Recent Uploaded Images</h2>
		<div id='slide' class="uk-flex-inline" uk-grid uk-lightbox="animation: slide"></div>
		<p class="uk-text-default uk-text-center"><a id="reload_recent" class="uk-button uk-button-default" href="#">Reload</a></p>
	</div>
<?php if($fromTW):?>
	<div id="tab-all" class="uk-width-1-1 imge-tab">
		<h2 class="uk-heading-medium">All Images</h2>
		<div id="all-images" class="uk-flex-inline" uk-grid uk-lightbox="animation: slide"></div>
		<p class="uk-text-center"><button id="load-more-images" type="button" class="uk-button uk-button-default">More</button></p>
	</div>
<?php endif;?>
	<div id="tab-about" class="uk-width-1-1 imge-tab">
		<h2 class="uk-heading-medium">About</h2>
		<ul class="uk-list uk-list-large">
			<li>This is imge.tw. Anonymous, AD-free, Cookieless Image-Host website.</li>
		</ul>
		<h3 class="uk-heading-small">Storage Status</h3>
		<ul class="uk-list uk-list-large">
			<li>Currently, there are <?=$info['total']?> images on imge.tw</li>
			<li>These images take <?=$info['space']?> storage space</li>
			<li>There are <?=$info['free']?> space free</li>
			<li><?=$info['percent']?>% <progress value="<?=$info['percent']?>" max="100" /></li>
		</ul>
<!--
		<h3 class="uk-heading-small">Discord</h3>
		<ul class="uk-list uk-list-large">
			<li>Join my <a href="https://discord.gg/nRkRyfF5" target="_blank">Discord</a> to discuss about imge.tw development.</li>
		</ul>
-->
	</div>
</div>
<?php include("_footer.tpl.php");?>
