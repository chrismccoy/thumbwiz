jQuery( thumbwiz_admin_page_ready() );

function thumbwiz_admin_page_ready() {

	if ( typeof pagenow !== 'undefined' ) {

		if ( pagenow == 'post' ) {
			if ( typeof wp.media != 'undefined' ) {
				wp.media.view.Modal.prototype.on(
					'open',
					function() {
						wp.media.frame.on(
							'selection:toggle',
							function() {
								if ( typeof wp.media.frame.state().get( 'selection' ).first() != 'undefined' ) {
									var attributes = wp.media.frame.state().get( 'selection' ).first().attributes;
									thumbwiz_attachment_selected( attributes );
								}
							}
						);
						wp.media.frame.on(
							'attachment:compat:ready',
							function() {
								if ( typeof wp.media.frame.state().get( 'selection' ).first() != 'undefined' ) {
									var attributes = wp.media.frame.state().get( 'selection' ).first().attributes;
									var thumb_id   = jQuery( '#thumbnail-' + attributes.id ).data( 'thumb_id' );
									if (
										jQuery( '#thumbnail-' + attributes.id ).data( 'featuredchanged' ) == true
										&& jQuery( '#attachments-' + attributes.id + '-featured' ).attr( 'checked' )
										&& thumb_id
									) {
										wp.media.featuredImage.set( thumb_id );
									}
									if ( jQuery('#attachments-' + attributes.id + '-thumbwiz-poster').val() === '' ) {
										jQuery('#attachments-' + attributes.id + '-thumbwiz-thumbtime').val('');
										jQuery('#attachments-' + attributes.id + '-thumbwiz-numberofthumbs').val(4);
										jQuery('#attachments-' + attributes.id + '-thumbnailplaceholder').empty();
										wp.media.featuredImage.remove();

									}
								}
							}
						);
						if ( typeof wp.media.frame.state() !== 'undefined'
							&& typeof wp.media.frame.state().get('library') !== 'undefined' ) {
							wp.media.frame.state().get( 'library' ).on(
								'reset',
								function() {
									wp.media.frame.trigger( 'selection:toggle' );
								}
							);
						}
					}
				);
			}
		}

		if ( pagenow == 'upload' ) {
			if ( typeof wp.media.view.Modal.prototype !== 'undefined' ) {
				wp.media.view.Modal.prototype.once(
					'open',
					function() {
						setTimeout(function(){
							if ( typeof wp.media.frame.model.attributes !== 'undefined' ) {
								var attributes = wp.media.frame.model.attributes;
								thumbwiz_attachment_selected( attributes );
								wp.media.frame.on(
									'refresh',
									function() {
										var attributes = wp.media.frame?.model?.attributes;
										if ( typeof attributes !== 'undefined' ) {
											thumbwiz_attachment_selected( attributes );
										}
									}
								);
							}
						},
					500 );
					}
				);
			}
		}

		if ( pagenow == 'attachment' ) {
			var attributes = {
				id:     jQuery( '#post_ID' ).val(),
				url:    jQuery( '#attachment_url' ).val()
			};
			thumbwiz_attachment_selected( attributes );

			jQuery('body').on('mouseup', '#remove-post-thumbnail', function(event) {
				setTimeout( function() {
					jQuery('#attachments-' + attributes.id + '-thumbwiz-poster').val('');
					jQuery('#attachments-' + attributes.id + '-thumbwiz-thumbtime').val('');
					jQuery('#attachments-' + attributes.id + '-thumbwiz-numberofthumbs').val(4);
					jQuery('#attachments-' + attributes.id + '-thumbnailplaceholder').empty();
				}, 100);
				jQuery(this).trigger('click');
			});
		}
	}

}

function thumbwiz_attachment_selected( attributes ) {

	if ( jQuery( '.thumbwiz_redraw_thumbnail_box' ).length ) {
		setTimeout( function(){ thumbwiz_redraw_thumbnail_box( attributes.id ) }, 5000 );
	}
}

function thumbwiz_convert_to_timecode(time, hours = false) {

	var time_display = '';

	if ( time ) {
		if ( hours ) {
			var hours = hours = Math.floor( time / 3600 );
			if ( hours < 10 ) {
				hours = "0" + hours;
			}
			var minutes = minutes = Math.floor( (time % 3600) / 60 );
		} else {
			var minutes = Math.floor( time / 60 );
		}
		var seconds = Math.round( time % 60 * 1000 ) / 1000;
		if ( minutes < 10 ) {
			minutes = "0" + minutes;
		}
		if ( seconds < 10 ) {
			seconds = "0" + seconds;
		}
		if ( hours ) {
			time_display = hours + ':' + minutes + ':' + seconds;
		} else {
			time_display = minutes + ':' + seconds;
		}

	}

	if ( time === 0 ) {
		if ( hours ) {
			time_display = '00:00:00';
		} else {
			time_display = '00:00';
		}
	}

	return time_display;

}

function thumbwiz_convert_from_timecode(timecode) {

	var thumbtimecode = 0;

	if ( timecode ) {

		var timecode_array = timecode.split( ":" );
		timecode_array     = timecode_array.reverse();
		if ( timecode_array[1] ) {
			timecode_array[1] = timecode_array[1] * 60;
		}
		if ( timecode_array[2] ) {
			timecode_array[2] = timecode_array[2] * 3600;
		}

		jQuery.each(
			timecode_array,
			function() {
				thumbtimecode += parseFloat( this );
			}
		);

	}

	return thumbtimecode;

}

function thumbwiz_break_video_on_close(postID) {

	var video = document.getElementById( 'thumb-video-' + postID );

	if ( video != null ) {

		var playButton = jQuery( ".thumbwiz-play-pause" );

		playButton.off( "click.thumbwiz" );
		video.preload = "none";
		video.src     = "";
		video.load();
		jQuery( video ).data( 'setup', false );
		jQuery( video ).data( 'busy', false );
	}

};

function thumbwiz_thumb_video_loaded(postID) { // sets up mini custom player for making thumbnails

	var video = document.getElementById( 'thumb-video-' + postID );

	if ( video != null ) {
		var crossDomainTest = jQuery.get( video.currentSrc )
			.fail(
				function(){
					jQuery( '#thumb-video-' + postID + '-container' ).hide();
					jQuery( '#thumb-video-' + postID ).data( 'allowed', 'off' );
					thumbwiz_break_video_on_close( postID );
				}
			);
	}

	jQuery( '#attachments-' + postID + '-thumbgenerate' ).prop( 'disabled', false ).attr( 'title', '' );
	jQuery( '#attachments-' + postID + '-thumbrandomize' ).prop( 'disabled', false ).attr( 'title', '' );
	jQuery( '#attachments-' + postID + '-thumbwiz-numberofthumbs' ).prop( 'disabled', false ).attr( 'title', '' );

	jQuery( '#thumb-video-' + postID + '-container' ).show();

	if ( video != null && jQuery( video ).data( 'setup' ) != true ) {

		if ( typeof wp !== 'undefined' ) {
			ed_id        = wp.media.editor.id();
			var ed_media = wp.media.editor.get( ed_id ); // Then we try to first get the editor
			ed_media     = 'undefined' != typeof( ed_media ) ? ed_media : wp.media.editor.add( ed_id ); // If it hasn't been created yet, we create it

			if ( ed_media ) {
				ed_media.on(
					'escape',
					function(postID) {
						return function() {
							if ( jQuery( '#show-thumb-video-' + postID + ' .thumbwiz-show-video' ).html() == thumbwiz_L10n.hidevideo ) {
								thumbwiz_reveal_thumb_video( postID );
							}
							// thumbwiz_break_video_on_close(postID);
						}
					}(postID)
				);
			}
		}

		video.removeAttribute( 'height' ); // disables changes made by mejs
		video.removeAttribute( 'style' );
		video.setAttribute( 'width', '200' );
		video.controls = '';

		var playButton   = jQuery( ".thumbwiz-play-pause" );
		var seekBar      = jQuery( ".thumbwiz-seek-bar" );
		var playProgress = jQuery( ".thumbwiz-play-progress" );
		var seekHandle   = jQuery( ".thumbwiz-seek-handle" );

		playButton.on(
			"click.thumbwiz",
			function() {
				if (video.paused == true) {
					// Play the video
					video.play();
				} else {
					// Pause the video
					video.pause();
					video.playbackRate = 1;
				}
			}
		);

		video.addEventListener(
			'play',
			function() {
				playButton.addClass( 'thumbwiz-playing' );
			}
		);

		video.addEventListener(
			'pause',
			function() {
				playButton.removeClass( 'thumbwiz-playing' );
			}
		);

		// update HTML5 video current play time
		video.addEventListener(
			'timeupdate',
			function() {
				var currentPos  = video.currentTime; // Get currenttime
				var maxduration = video.duration; // Get video duration
				var percentage  = 100 * currentPos / maxduration; // in %
				playProgress.css( 'width', percentage + '%' );
				seekHandle.css( 'left', percentage + '%' );
			}
		);

		var timeDrag = false;   /* Drag status */
		seekBar.on(
			'mousedown',
			function(e) {
				if ( video.paused == false ) {
					video.pause();
				}

				if ( video.currentTime == 0 ) {
					video.play(); // video won't seek in Chrome unless it has played once already
				}

				timeDrag = true;
				updatebar( e.pageX );
			}
		);
		jQuery( document ).on(
			'mouseup',
			function(e) {
				if (timeDrag) {
					timeDrag = false;
					updatebar( e.pageX );
				}
			}
		);
		jQuery( document ).on(
			'mousemove',
			function(e) {
				if (timeDrag) {
					updatebar( e.pageX );
				}
			}
		);
		// update Progress Bar control
		var updatebar = function(x) {
			var maxduration = video.duration; // Video duraiton
			var position    = x - seekBar.offset().left; // Click pos
			var percentage  = 100 * position / seekBar.width();
			// Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}
			// Update progress bar and video currenttime
			playProgress.css( 'width', percentage + '%' );
			seekHandle.css( 'left', percentage + '%' );
			video.currentTime = maxduration * percentage / 100;

		};

		jQuery( video ).on(
			'loadedmetadata',
			function() {
				var currentTimecode = jQuery( '#attachments-' + postID + '-thumbwiz-thumbtime' ).val();
				if ( currentTimecode ) {
					video.currentTime = thumbwiz_convert_from_timecode( currentTimecode );
				}
			}
		);

		jQuery( '.thumbwiz-video-controls' ).on(
			'keydown.thumbwiz',
			function(e) {

				e.stopImmediatePropagation();

				switch (e.which) {
					case 32: // spacebar
						playButton.click();
					break;

					case 37: // left
						video.pause();
						video.currentTime = video.currentTime - 0.042;
					break;

					case 39: // right
						video.pause();
						video.currentTime = video.currentTime + 0.042;
					break;

					case 74: //j
						if ( video.paused == false ) {
							video.playbackRate = video.playbackRate - 1;
						}
						if ( video.playbackRate >= 0 ) {
							video.playbackRate = -1;
						}
						video.play();
					break;

					case 75: // k
						if ( video.paused == false ) {
							playButton.click();
						}
					break;

					case 76: //l
						if ( video.paused == false ) {
							video.playbackRate = video.playbackRate + 1;
						}
						if ( video.playbackRate <= 0 ) {
							video.playbackRate = 1;
						}
						video.play();
					break;

					default: return; // exit this handler for other keys
				}
				e.preventDefault(); // prevent the default action (scroll / move caret)
			}
		);

		jQuery( video ).on(
			'click',
			function(e){
				e.stopImmediatePropagation();
				playButton.click();
				jQuery( '.thumbwiz-video-controls' ).trigger( 'focus' );
			}
		);

		jQuery( '.thumbwiz-video-controls' ).trigger( 'focus' );
		jQuery( video ).data( 'setup', true );
		if ( jQuery( video ).data( 'busy' ) != true ) {
			thumbwiz_break_video_on_close( postID );
		}
	}
}

function thumbwiz_draw_thumb_canvas(canvas, canvas_source) {

	if ( canvas_source.nodeName.toLowerCase() === 'video' ) {
		canvas_width  = canvas_source.videoWidth;
		canvas_height = canvas_source.videoHeight;
	} else {
		canvas_width  = canvas_source.width;
		canvas_height = canvas_source.height;
	}

	canvas.width  = canvas_width;
	canvas.height = canvas_height;
	var context   = canvas.getContext( '2d' );
	context.fillRect( 0, 0, canvas_width, canvas_height );
	context.drawImage( canvas_source, 0, 0, canvas_width, canvas_height );

	return canvas;

}

function thumbwiz_remove_mejs_player(postID) {

	if ( jQuery( '#thumb-video-' + postID + '-player .mejs-container' ).attr( 'id' ) !== undefined
		&& typeof mejs !== 'undefined'
	) { // this is the Media Library pop-up introduced in WordPress 4.0

		var mejs_id     = jQuery( '#thumb-video-' + postID + '-player .mejs-container' ).attr( 'id' );
		var mejs_player = eval( 'mejs.players.' + mejs_id );
		if ( typeof mejs_player !== 'undefined' ) {
			if ( ! mejs_player.paused ) {
				mejs_player.pause();
			}
			mejs_player.remove();
		}

	}

}

function thumbwiz_reveal_thumb_video(postID) {

	jQuery( '#show-thumb-video-' + postID + ' :first' ).toggleClass( 'thumbwiz-down-arrow thumbwiz-right-arrow' );
	var text = jQuery( '#show-thumb-video-' + postID + ' .thumbwiz-show-video' );

	if ( text.html() == thumbwiz_L10n.choosefromvideo ) { // video is being revealed

		thumbwiz_remove_mejs_player( postID );

		video = document.getElementById( 'thumb-video-' + postID );
		jQuery( video ).data( 'busy', true );
		video.src = document.getElementsByName( 'attachments[' + postID + '][thumbwiz-url]' )[0].value;
		jQuery( video ).attr( "preload", "metadata" );
		video.load();

		setTimeout(
			function(){ // wait for video to start loading

				if ( video.networkState == 1 || video.networkState == 2 ) {
					text.html( thumbwiz_L10n.hidevideo );
					jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).slideUp();
					jQuery( video ).on(
						'timeupdate.thumbwiz',
						function() {
							if (video.currentTime != 0) {
								var thumbtimecode = thumbwiz_convert_to_timecode( document.getElementById( 'thumb-video-' + postID ).currentTime );
								jQuery( '#attachments-' + postID + '-thumbwiz-thumbtime' ).val( thumbtimecode );
							}
						}
					);
				} else {

					text.html( thumbwiz_L10n.cantloadvideo );
					jQuery( '#thumb-video-' + postID + '-player' ).hide();
					jQuery( '#show-thumb-video-' + postID + ' :first' ).hide();

				}

			},
			1000
		);
	} else if ( text.html() == thumbwiz_L10n.hidevideo ) { // video is being hidden

		video = document.getElementById( 'thumb-video-' + postID );
		video.pause();
		jQuery( '#thumb-video-' + postID ).off( 'timeupdate.thumbwiz' );
		thumbwiz_break_video_on_close( postID );
		text.html( thumbwiz_L10n.choosefromvideo );

		if ( jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).is( ":visible" ) == false ) {
			jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).slideDown();
		}

	}

	if ( text.html() != thumbwiz_L10n.cantloadvideo ) {

		jQuery( '#thumb-video-' + postID + '-player' ).animate( {opacity: 'toggle', height: 'toggle'}, 500 );
		jQuery( '#generate-thumb-' + postID + '-container' ).animate( {opacity: 'toggle', height: 'toggle'}, 500 );

	}

}

function thumbwiz_first_frame( postID ) {
	document.getElementById( 'attachments-' + postID + '-thumbwiz-numberofthumbs' ).value = 1;
	document.getElementById( 'attachments-' + postID + '-thumbwiz-thumbtime' ).value = '0';
	thumbwiz_generate_thumb(postID, 'generate');
}

function thumbwiz_generate_thumb(postID, buttonPushed) {

	var howmanythumbs    = document.getElementById( 'attachments-' + postID + '-thumbwiz-numberofthumbs' ).value;
	var firstframethumb  = false;
	var specifictimecode = document.getElementsByName( 'attachments[' + postID + '][thumbwiz-thumbtime]' )[0].value;

	if ( specifictimecode === "0" ) {
		specifictimecode = 0;
		firstframethumb = true;
		howmanythumbs = 1;
	}
	if ( buttonPushed == "random" || howmanythumbs > 1 ) {
		specifictimecode = 0;
	}
	if ( specifictimecode != 0 ) {
		howmanythumbs = 1;
	}

	var thumbnailplaceholderid = "#attachments-" + postID + "-thumbnailplaceholder";
	var thumbnailboxID         = "#attachments-" + postID + "-thumbwiz-thumbnailbox";
	var thumbnailboxoverlayID  = "#attachments-" + postID + "-thumbwiz-thumbnailboxoverlay";
	var cancelthumbdivID       = '#attachments-' + postID + '-thumbwiz-cancelthumbsdiv';
	var i                      = 1;
	var increaser              = 0;
	var iincreaser             = 0;
	var video_id               = 'thumb-video-' + postID;

	thumbwiz_remove_mejs_player( postID );

	if ( jQuery( '#' + video_id ).data( 'allowed' ) == true ) {

		video = document.getElementById( video_id );

		if ( video.preload == "none" ) {

			video.src     = document.getElementsByName( 'attachments[' + postID + '][thumbwiz-url]' )[0].value;
			video.preload = "metadata";
			video.load();
			jQuery( video ).data( 'busy', true );
			jQuery( video ).data( 'success', false );

			jQuery( video ).on(
				"loadedmetadata.thumbwiz",
				function() {
					jQuery( video ).data( 'success', true );
					thumbwiz_make_canvas_thumbs_loop();
				}
			);

		} else {
			thumbwiz_make_canvas_thumbs_loop();
		}

	}

	jQuery( thumbnailplaceholderid ).empty();
	jQuery( thumbnailplaceholderid ).append( '<strong>' + thumbwiz_L10n.choosethumbnail + ' </strong><div style="display:inline-block;" id="attachments-' + postID + '-thumbwiz-cancelthumbsdiv" name="attachments-' + postID + '-thumbwiz-cancelthumbsdiv"> <input type="button" id="attachments-' + postID + '-thumbwiz-cancelencode" class="button-secondary" value="Cancel Generating" name="attachments-' + postID + '-cancelencode" onclick="thumbwiz_cancel_thumbs(\'' + postID + '\');"></div><div id="attachments-' + postID + '-thumbwiz-thumbnailboxoverlay" name="attachments-' + postID + '-thumbwiz-thumbnailboxoverlay" class="thumbwiz_thumbnail_overlay"><div name="attachments-' + postID + '-thumbwiz-thumbnailbox" id="attachments-' + postID + '-thumbwiz-thumbnailbox" class="thumbwiz_thumbnail_box"></div></div>' );

	function thumbwiz_make_canvas_thumbs_loop() {

		if (video.networkState == 1
			|| video.networkState == 2
		) { // if the browser can load the video, use it to make thumbnails

			var thumbnails   = [];

			jQuery( '#' + video_id ).on(
				'seeked.thumbwiz',
				function(){ // when the video is finished seeking

					var thumbnail_saved = jQuery( video ).data( 'thumbnail_data' );
					if ( typeof thumbnail_saved !== 'undefined'
						&& thumbnail_saved.length > 0
					) { // if there are any thumbnails that haven't been generated

						if ( video.paused == false ) {
							video.pause();
						}

						time_id = Math.round( video.currentTime * 100 );

						jQuery( thumbnailboxID ).append( '<div style="display:none;" class="thumbwiz_thumbnail_select" name="attachments[' + postID + '][thumb' + time_id + ']" id="attachments-' + postID + '-thumb' + time_id + '"><label for="flashmedia-' + postID + '-thumbradio' + time_id + '"><canvas class="thumbwiz_thumbnail" id="' + postID + '_thumb_' + time_id + '" data-movieoffset="' + video.currentTime + '"></canvas></label><br /><input type="radio" name="attachments[' + postID + '][thumbradio' + time_id + ']" id="flashmedia-' + postID + '-thumbradio' + time_id + '" value="' + video.currentTime + '" onchange="thumbwiz_save_canvas_thumb(\'' + postID + '\', \'' + time_id + '\', 1, 0);"></div>' );
						var canvas = document.getElementById( postID + '_thumb_' + time_id );
						canvas     = thumbwiz_draw_thumb_canvas( canvas, video );
						jQuery( '#attachments-' + postID + '-thumb' + time_id ).animate( {opacity: 'toggle', height: 'toggle', width: 'toggle'}, 1000 );

						thumbnail_saved.splice( 0,1 );
						jQuery( video ).data( 'thumbnail_data', thumbnail_saved );
						if ( thumbnail_saved.length > 0 ) {
							video.currentTime = thumbnail_saved[0];
						} else {
							jQuery( video ).off( 'seeked.thumbwiz' );
							jQuery( video ).off( 'loadedmetadata.thumbwiz' );
							video.preload = "none";
							video.load();
							jQuery( thumbnailboxoverlayID ).fadeTo( 2000, 1 );
							jQuery( cancelthumbdivID ).animate( {opacity: 0, height: 'toggle'}, 500 );
							jQuery( video ).removeData( 'thumbnail_data' );
							thumbwiz_break_video_on_close( postID );
						}
					}
				}
			);

			for ( i; i <= howmanythumbs; i++ ) {
				iincreaser = i + increaser;
				increaser++;
				var movieoffset = Math.round( (video.duration * iincreaser) / (howmanythumbs * 2) * 100 ) / 100;

				if (buttonPushed == "random") { // adjust offset random amount
					var random_offset = Math.round( Math.random() * video.duration / howmanythumbs );
					movieoffset       = movieoffset - random_offset;
					if (movieoffset < 0) {
						movieoffset = 0;
					}
				}

				thumbnails.push( movieoffset ); // add offset to array
			}

			if ( firstframethumb ) {
				thumbnails[0] = 0;
			}

			if ( specifictimecode ) {
				var thumbtimecode = thumbwiz_convert_from_timecode( specifictimecode );
				thumbnails        = [thumbtimecode];
			}

			video.play();

			jQuery( video ).on(
				'loadeddata',
				function(){
					var thumbnail_saved = jQuery( video ).data( 'thumbnail_data' );
					if ( typeof thumbnail_saved !== 'undefined'
						&& thumbnail_saved.length > 0
					) {
						video.currentTime = thumbnail_saved[0];
					}
				}
			);

			jQuery( video ).data( 'thumbnail_data', thumbnails );

		}

	}//end canvas thumb function
}

function thumbwiz_change_media_library_video_poster(post_id, thumb_url) {

	if ( ( jQuery( 'div[data-id=' + post_id + '] .wp-video-shortcode.mejs-container' ).length > 0
		|| jQuery('.thumbnail.thumbnail-video .wp-video-shortcode.mejs-container').length > 0 )
		&& typeof mejs !== 'undefined'
	) {
		if ( jQuery( 'div[data-id=' + post_id + '] .wp-video-shortcode.mejs-container' ).length > 0 ) {
			var mejs_id = jQuery( 'div[data-id=' + post_id + '] .wp-video-shortcode.mejs-container' ).attr( 'id' );
		} else if ( jQuery('.thumbnail.thumbnail-video .wp-video-shortcode.mejs-container').length > 0 ) {
			var mejs_id = jQuery('.thumbnail.thumbnail-video .wp-video-shortcode.mejs-container').attr( 'id' );
		}
		var mejs_player = mejs.players[mejs_id];
		mejs_player.setPoster( thumb_url );
	}
}

function thumbwiz_save_canvas_thumb(postID, time_id, total, index) {

	var thumbwiz_security = document.getElementsByName( 'attachments[' + postID + '][thumbwiz-security]' )[0].value;

	var video_url    = document.getElementsByName( 'attachments[' + postID + '][thumbwiz-url]' )[0].value;
	var canvas       = document.getElementById( postID + '_thumb_' + time_id );
	var png64dataURL = canvas.toDataURL( 'image/jpeg', 0.8 ); // this is what saves the image. Do this after selection.

	jQuery( '#attachments-' + postID + '-thumbnailplaceholder canvas' ).fadeTo( 500, .25 );
	jQuery( '#attachments-' + postID + '-thumbnailplaceholder input' ).prop( 'disabled', true );
	jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).prepend( '<div class="thumbwiz_save_overlay">' + thumbwiz_L10n.saving + '</div>' )

	jQuery.ajax(
		{
			type: "POST",
			url: ajaxurl,
			data: { action:"thumbwiz_save_html5_thumb",
				security: thumbwiz_security,
				url: video_url,
				offset: time_id,
				postID: postID,
				total: total,
				index: index,
				raw_png: png64dataURL
			},
			dataType: 'json'
		}
	)
	.done(
		function(data) {
			if ( data && data.thumb_url && data.thumb_id ) {
				document.getElementsByName( 'attachments[' + postID + '][thumbwiz-autothumb-error]' )[0].value = '';

				jQuery( '#attachments-' + postID + '-thumbwiz-numberofthumbs' ).val( '1' );

				var time_display = thumbwiz_convert_to_timecode( canvas.dataset.movieoffset );
				jQuery( '#attachments-' + postID + '-thumbwiz-thumbtime' ).val( time_display );
				jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box"><img width="200" src="' + png64dataURL + '"></div>' );
				jQuery( '#attachments-' + postID + '-thumbwiz-poster' ).val( data.thumb_url ).trigger( 'change' );
				thumbwiz_change_media_library_video_poster( postID, png64dataURL );
				if ( data.thumb_id && jQuery('#set-post-thumbnail').length ) {
					wp.media.featuredImage.set( data.thumb_id );
				}
			}
			else {
				jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box">' + thumbwiz_L10n.write_error + '</div>' );
			}
		}
	)
	.fail(
		function(xhr, textStatus, errorThrown) {
			document.getElementsByName( 'attachments[' + postID + '][thumbwiz-autothumb-error]' )[0].value = errorThrown;
			jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).empty();
			jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box">' + errorThrown + '</div>' );
		}
	);
}

function thumbwiz_thumb_video_manual(postID) {

	var video = document.getElementById( 'thumb-video-' + postID );

	if ( jQuery( '#thumb-video-' + postID + '-player .mejs-container' ).attr( 'id' ) !== undefined ) { // this is the Media Library pop-up introduced in WordPress 4.0;
		video = document.getElementById( 'thumb-video-' + postID + '_html5' );
	}

	var time_id      = Math.round( video.currentTime );
	var time_display = thumbwiz_convert_to_timecode( video.currentTime );

	jQuery( '#thumb-video-' + postID + '-player .button-secondary' ).prop( 'disabled', true );
	jQuery( '#thumb-video-' + postID + '-player' ).fadeTo( 500, .25 );
	jQuery( '#thumb-video-' + postID + '-container' ).prepend( '<div id="thumbwiz-save-'+ postID + '-thumb-manual" class="thumbwiz_save_overlay">' + thumbwiz_L10n.saving + '</div>' );

	jQuery( '#attachments-' + postID + '-thumbwiz-thumbtime' ).val( time_display );

	jQuery( "#attachments-" + postID + "-thumbnailplaceholder" ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box"><canvas id="' + postID + '_thumb_' + time_id + '" data-movieoffset="' + video.currentTime + '"></canvas></div>' );

	var canvas = document.getElementById( postID + '_thumb_' + time_id );
	canvas     = thumbwiz_draw_thumb_canvas( canvas, video );

	setTimeout(
		function() { // redraw the canvas after a delay to avoid Safari bug
			canvas = thumbwiz_draw_thumb_canvas( canvas, video );
			thumbwiz_save_canvas_thumb( postID, time_id, 1, 0 );
			jQuery('#thumbwiz-save-'+ postID + '-thumb-manual').remove();
			jQuery( '#thumb-video-' + postID + '-player' ).fadeTo( 500, 1 );
			thumbwiz_reveal_thumb_video( postID );
		},
		250
	);

}

function thumbwiz_cancel_thumbs(postID) {

		var thumbnailplaceholderid = "#attachments-" + postID + "-thumbnailplaceholder";
		var thumbnailboxoverlayID  = "#attachments-" + postID + "-thumbwiz-thumbnailboxoverlay";
		var cancelthumbdivID       = '#attachments-' + postID + '-thumbwiz-cancelthumbsdiv';
		var thumbnailTimeout     = jQuery( thumbnailplaceholderid ).data( "thumbnailTimeouts" );

	for ( key in thumbnailTimeout ) {
		clearTimeout( thumbnailTimeout[key] ); }
		jQuery( '#thumb-video-' + postID ).off( 'seeked.thumbwiz' );
		jQuery( '#thumb-video-' + postID ).data( 'thumbnail_data', [] );
		jQuery( thumbnailplaceholderid ).data( "thumbnailTimeouts", null );

		jQuery( thumbnailboxoverlayID ).fadeTo( 2000, 1 );
		jQuery( cancelthumbdivID ).animate( {opacity: 0, height: 'toggle'}, 500 );

}

function thumbwiz_redraw_thumbnail_box(postID) {

	var thumbwiz_security = document.getElementsByName( 'attachments[' + postID + '][thumbwiz-security]' );

	if ( thumbwiz_security.length
		&& typeof thumbwiz_security[0].value != 'undefined'
	) { // sometimes this tries to run after the media modal is closed

		jQuery.post(
			ajaxurl,
			{ action:"thumbwiz_redraw_thumbnail_box",
				security: thumbwiz_security[0].value,
				post_id: postID
			},
			function(data) {
				if ( data.thumb_url ) {
					jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box"><img width="200" src="' + data.thumb_url + '?' + Math.floor( Math.random() * 10000 ) + '"></div>' );
					jQuery( '#attachments-' + postID + '-thumbwiz-poster' ).val( data.thumb_url );
					if ( data.thumbnail_size_url ) {
						basename = data.thumb_url.substring( data.thumb_url.lastIndexOf( '/' ) + 1, data.thumb_url.indexOf( '_thumb' ) )
						jQuery( '.attachment-preview.type-video:contains(' + basename + ')' ).parent().find( 'img' )
						.attr( 'src', data.thumbnail_size_url + '?' + Math.floor( Math.random() * 10000 ) )
						.css( 'width', '100%' )
						.css( 'height', '100%' )
						.css( 'padding-top', '0' );
					}
					if ( data.thumb_id ) {
						wp.media.featuredImage.set( data.thumb_id );
					}
					jQuery( '#attachments-' + postID + '-thumbwiz-poster' ).trigger( 'change' );

				} else if ( data.thumb_error ) {
					jQuery( '#attachments-' + postID + '-thumbnailplaceholder' ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box"><span>' + data.thumb_error + '</span></div>' );
				} else {
					setTimeout( function(){ thumbwiz_redraw_thumbnail_box( postID ) }, 5000 );
				}

			},
			"json"
		);

	}
}

function thumbwiz_pick_image(button) {

		var frame;

		jQuery(
			function() {

				// Build the choose from library frame.

				var $el = jQuery( button );
				if ( typeof event !== 'undefined' ) {
					event.preventDefault();
				}

				// If the media frame already exists, reopen it.
				if ( frame ) {
					frame.open();
					return;
				}

				// Create the media frame.
				frame = wp.media.frames.customHeader = wp.media(
					{
						// Set the title of the modal.
						title: $el.data( 'choose' ),

						// Tell the modal to show only images.
						library: {
							type: 'image'
						},

						// Customize the submit button.
						button: {
							// Set the text of the button.
							text: $el.data( 'update' ),
							close: true
						}
					}
				);

				// When an image is selected, run a callback.
				frame.on(
					'select',
					function() {
						// Grab the selected attachment.
						var attachment = frame.state().get( 'selection' ).first();
						jQuery( '#' + $el.data( 'change' ) ).val( attachment.attributes.url );
						if ( $el.data( 'change' ).substring( -25 ) == "thumbwiz-poster" ) {
							jQuery( '#' + $el.data( 'change' ).slice( 0, -25 ) + 'thumbnailplaceholder' ).html( '<div class="thumbwiz_thumbnail_box thumbwiz_chosen_thumbnail_box"><img width="200" src="' + attachment.attributes.url + '"></div>' );
						}
						jQuery( '#' + $el.data( 'change' ) ).trigger( 'change' );
					}
				);

				frame.open();
			}
		);

}
