function changePage(pageNumber, itemCount, itemsPerPage, maxPage, prefix,
		width, height, query, url, user, tags, tagMode, forceReload) {
	
	if (jQuery('#' + prefix + ' > #' + prefix + '-page' + pageNumber).length == 0 || forceReload) {
		if (jQuery('body').attr('data-is-draft')) {
			var isDraft = true;
		} else {
			var isDraft = false;
		}
		jQuery('#' + prefix + ' > .progress-gallery').removeClass('hide');
		jQuery('#' + prefix + ' > .active-items').hide();
		var request = jQuery.ajax({
			url : url,
			type : "POST",
			data : {
				'page' : pageNumber,
				'itemCount' : itemCount,
				'itemsPerPage' : itemsPerPage,
				'maxPage' : maxPage,
				'width' : (width) ? width : null,
				'height' : (height) ? height : null,
				'prefix' : prefix,
				'query' : (query) ? query : null,
				'user' : (user) ? user : null,
				'tags' : (tags) ? tags : null,
				'tagMode' : (tagMode) ? tagMode : null,
				'is-draft' : isDraft,
				'current-page' : jQuery('body').attr('data-current-page')
			},
			dataType : "json"
		});

		request.done(function(data) {
			jQuery('#' + prefix + ' > .progress-gallery').addClass('hide');
			var newHtml = data.html;
			jQuery('#' + prefix).append(newHtml);
			jQuery('#' + prefix + ' > .active-items').removeClass('active-items');
			jQuery('#' + prefix + ' > #' + prefix + '-page' + pageNumber).show();
			jQuery('#' + prefix + ' > #' + prefix + '-page' + pageNumber).addClass('active-items');
		});

		request.fail(function(jqXHR, textStatus) {
					jQuery('#' + prefix + ' > .progress-gallery').addClass(
							'hide');
					var errorHtml = '<div class="alert alert-error"><button type="button" class="close" data-dismiss="alert">&times;</button><h4>'+jQuery("body").attr("data-error")+'</h4>+jQuery("body").attr("data-noimageerror")+</div>';
					jQuery('#' + prefix).prepend(errorHtml);
					jQuery('#' + prefix + ' > .active-items').show();
					console.log(textStatus);
		});
	} else {
		jQuery('#' + prefix + ' > .active-items').hide();
		jQuery('#' + prefix + ' > .active-items').removeClass('active-items');
		jQuery('#' + prefix + ' > #' + prefix + '-page' + pageNumber).show();
		jQuery('#' + prefix + ' > #' + prefix + '-page' + pageNumber).addClass(
				'active-items');
	}

	return false;
}

/**
 * Display the modal with the title and the picture given in arguments
 * 
 * @param src Url of the picture
 * @param title Title of the picture
 */
function callModal(src, title) {
	var bodyMaxHeight = (window.innerHeight*(100/100)) - 200;
	var imgMaxHeight = bodyMaxHeight - 30;
	
	//Set title of the modal
	jQuery('#myModal #myModalLabel').html(title);
	
	//Set URL of the img tag
	jQuery('#myModal #fullScreenPicture').attr('src', src);
	
	//Set max height of the body and the image
	jQuery("#myModal .modal-body").css("max-height", bodyMaxHeight+"px");
	jQuery("#myModal .modal-body img").css("max-height", imgMaxHeight+"px");
	
	//Center image
	jQuery("#myModal .modal-body").css("text-align", "center");
	
	//Display modal
	jQuery("#myModal").modal();
	
	return false;
}

jQuery(window).load(function() {

	jQuery(".flickr-gallery").each(function() {
		var prefix = this.id;
		var allFlickrCount = jQuery(this).attr("data-count");
		var pageSize = jQuery(this).attr("data-pagesize");
		var maxPage = jQuery(this).attr("data-maxpage");
		var url = jQuery(this).attr("data-url");
		var user = jQuery(this).attr("data-user");
		var tags = jQuery(this).attr("data-tags");
		var tagMode = jQuery(this).attr("data-tagmode");
		
		if(user != "") {
			changePage(1, allFlickrCount, pageSize, maxPage, prefix, null, null, null, url, user, null, null, true);
		} else {
			changePage(1, allFlickrCount, pageSize, maxPage, prefix, null, null, null, url, null, tags, tagMode, true);
		}
	});
	
});
