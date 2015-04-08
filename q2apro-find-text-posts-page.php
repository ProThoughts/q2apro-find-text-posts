<?php
/*
	Plugin Name: Find Text in Posts
	Plugin URI: http://www.q2apro.com/plugins/find-text-posts
*/

	class q2apro_find_text_posts_page {
		
		var $directory;
		var $urltoroot;
		
		function load_module($directory, $urltoroot)
		{
			$this->directory=$directory;
			$this->urltoroot=$urltoroot;
		}
		
		// for display in admin interface under admin/pages
		function suggest_requests() 
		{	
			return array(
				array(
					'title' => 'Find Text in Posts', // title of page
					'request' => 'findtext', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}
		
		// for url query
		function match_request($request)
		{
			if ($request=='findtext') {
				return true;
			}

			return false;
		}

		function process_request($request)
		{
		
			// return if not admin level
			$level=qa_get_logged_in_level();
			if ($level<QA_USER_LEVEL_ADMIN) {
				$qa_content = qa_content_prepare();
				$qa_content['custom'] = '<p>'.qa_lang('q2apro_find_text_posts_lang/not_allowed').'</p>';
				return $qa_content;
			}

			// AJAX post: we received post data, so it should be the ajax call
			$searchstring = qa_post_text('ajaxdata');
			
			if(!empty($searchstring)) {
				
				$ajaxreturn = '';
				
				$questionid = null;
				$urlparams = null;
				$postanchor = null;
				$qTitle = null;
				$postcontent = '';
				$posttype_label = '';
				
				// get type, title and content of post
				$postdatas = qa_db_read_all_assoc(
								qa_db_query_sub('SELECT postid, type, title, content, userid FROM `^posts` 
												WHERE `content` LIKE #
												OR `title` LIKE #
												', '%'.$searchstring.'%', '%'.$searchstring.'%')
							 );

				foreach($postdatas as $postdata) {
				
					$posttype = $postdata['type'];
					if(empty($posttype)) {
						$ajaxreturn .= '<p>'.qa_lang('q2apro_find_text_posts_lang/error2').'</p>';
						echo $ajaxreturn;
						return;
					}
					// save content for output
					$postcontent = $postdata['content'];
					
					if($posttype=='Q') {
						$qTitle = $postdata['title'];
						$questionid = $postdata['postid'];
						$posttype_label = qa_lang('q2apro_find_text_posts_lang/question');
					}
					else {
						// no question, post must be A or C
						// $posttype=='A' || $posttype=='C' 
						if($posttype=='A') {
							$posttype_label = qa_lang('q2apro_find_text_posts_lang/answer');
						}
						else if($posttype=='C') {
							$posttype_label = qa_lang('q2apro_find_text_posts_lang/comment');
						}
						// set anchor for url
						$postanchor = qa_anchor($posttype, $postdata['postid']);
						$urlparams = array('show' => $postdata['postid']);

						// get parentid of post (must be question or answer)
						$parentid = qa_db_read_one_value( 
											qa_db_query_sub('SELECT parentid FROM `^posts` 
																WHERE `postid` = # 
																LIMIT 1', $postdata['postid']), true);
						
						// check if parent is question or answer
						$parentpost = qa_db_read_one_assoc( 
											qa_db_query_sub('SELECT type, title, parentid FROM `^posts` 
																WHERE `postid` = # 
																LIMIT 1', $parentid), true );
						
						// questionid
						$parenttype = $parentpost['type'];
						if($parenttype=='Q') {
							// question
							$questionid = $parentid;
							$qTitle = $parentpost['title'];
						}
						else if($parenttype=='A') {
							// answer, we need to query again to receive the question id
							$question = qa_db_read_one_assoc( 
												qa_db_query_sub('SELECT postid, title FROM `^posts` 
																	WHERE `postid` = # 
																	AND `type` = "Q"
																	LIMIT 1', $parentpost['parentid']) );
							$questionid = $question['postid'];
							$qTitle = $question['title'];
						}
						else {
							// content output error
							$ajaxreturn .= '<p>'.qa_lang('q2apro_find_text_posts_lang/error1').'</p>';
							echo $ajaxreturn;
							return;
						}
					} // end A or C
					
					// if everything is alright, we have the questionid and return the data
					if(isset($questionid)) {
						if(empty($qTitle)) $qTitle = '';
						// function qa_path($request, $params=null, $rooturl=null, $neaturls=null, $anchor=null)
						$posturl = qa_path(qa_q_request($questionid, $qTitle), $urlparams, qa_opt('site_url'), null, $postanchor);
						// ?show=10081#a10081
						$postlink = '<a target="_blank" href="'.$posturl.'">'.$qTitle.'</a>';

						$postcreator = qa_lang('q2apro_find_text_posts_lang/anonymous');
						$postcreatorlink = $postcreator;
						if(isset($postdata['userid'])) {
							$postcreator = qa_db_read_one_value(qa_db_query_sub('SELECT handle FROM ^users WHERE `userid` = #', $postdata['userid']), true);
							$postcreatorlink = '<a href="'.qa_path('user').'/'.$postcreator.'">'.$postcreator.'</a>';
						}
						
						$ajaxreturn .= '
									<div class="ajaxresult_item">
										<p style="font-weight:bold;">'.qa_lang('q2apro_find_text_posts_lang/gotpost').'</p>
										<div class="linkresult">
										<p><span>'.qa_lang('q2apro_find_text_posts_lang/posttype').'</span> <span>'.$posttype_label.'</span></p>
										<p><span>'.qa_lang('q2apro_find_text_posts_lang/postcreator').'</span> <span>'.$postcreatorlink.'</span></p>
										<p><span>'.qa_lang('q2apro_find_text_posts_lang/postlink').'</span> <span>'.$postlink.'</span></p>
										<p><span>'.qa_lang('q2apro_find_text_posts_lang/posturl').'</span> <span>'.$posturl.'</span></p>
										</div>
										<p id="postcontent_head">'.qa_lang('q2apro_find_text_posts_lang/postcontent').'</p> 
										<div id="postcontent">'.$postcontent.'</div>
									</div> <!-- ajaxresult_item -->';
					}
					else {
						// content output error
						$ajaxreturn .= '<p>'.qa_lang('q2apro_find_text_posts_lang/error2').'</p>';
						return;
					}
					
				} // end foreach($postdatas
				
				$ajaxreturn = '<p id="ajaxresultcount">Results: '.count($postdatas).'</p>'.$ajaxreturn;
				echo $ajaxreturn;
				return;
			} // end AJAX return


			// start content
			$qa_content = qa_content_prepare();

			// page title
			$qa_content['title'] = qa_lang('q2apro_find_text_posts_lang/page_title'); 

			// some CSS styling
			$qa_content['custom'] = '<style type="text/css">
				#indiv { 
					border-left:10px solid #ABF;
					margin:20px 0 0 5px;
					padding:5px 10px; 
				}
				.qa-main h1 { 
					margin-bottom:40px; 
				}
				input#searchstring_input { 
					display:inline-block;
					width:200px; 
					border:1px solid #EEE; 
					padding:3px; 
					margin-bottom:15px;
				}
				#ajax_loadindicator {
					display:inline-block;
					margin-left:15px;
				}
				#ajaxresultcount {
					margin:20px 0 0 25px;
					font-size:18px;
				}
				#ajaxresult {
				}
				.ajaxresult_item {
					padding:10px;
					margin:25px;
					border:1px solid #EEE;
				}
				.ajaxresult_item:nth-child(odd) {
					background:#FFF;
				}
				.ajaxresult_item:nth-child(even) {
					background:#F0F0F0;
				}
				.linkresult { 	
					display:table;
				}
				.linkresult p { 
					display: table-row; 
				}
				.linkresult span { 
					display: table-cell; 
					padding-right:10px; 
					line-height:200%; 
					min-width:70px;
				}
				#postcontent_head {
					border-top:1px solid #555;
					padding-top:15px;
					margin:15px 0 10px 0;
					font-weight:bold;
				}
				.wordhighlight {
					background:#FF0;
				}
			</style>';
			
			// default page with input dialog
			$qa_content['custom'] .= '<div id="indiv">
											<input name="searchstring_input" id="searchstring_input" type="text" placeholder="'.qa_lang('q2apro_find_text_posts_lang/input_placeholder').'" autofocus>
											<div id="ajax_loadindicator">'.qa_lang('q2apro_find_text_posts_lang/searching').' <span class="dots"></span> </div>
											<br />
											<span class="btnblue" id="submitbtn">'.qa_lang('q2apro_find_text_posts_lang/submitbutton').'</span>
										 </div>';
			$qa_content['custom'] .= '<div id="ajaxresult"></div>';
			
			$qa_content['custom'] .= '
			<script type="text/javascript">
				$(document).ready(function(){
					$("#submitbtn").click( function() { 
						doAjaxPost();
					}); // end click
					$("#searchstring_input").keyup(function(e) {
						// if enter key
						if(e.which == 13) { 
							doAjaxPost();
						}
					});

					function doAjaxPost() {
						// get searchstring from input
						var ajaxsearchstring = $("#searchstring_input").val(); 
						// show load indicator
						$("#ajax_loadindicator").show();
						animateLoading();
						
						// send ajax request
						$.ajax({
							 type: "POST",
							 url: "'.qa_self_html().'",
							 data: { ajaxdata: ajaxsearchstring },
							 cache: false,
							 success: function(data) {
								console.log("got server data");
								// console.log("server returned:"+data);
								$("#ajax_loadindicator").hide();
								// output result in DIV
								$("#ajaxresult").html( data );
								$("#ajaxresult *").highlight(ajaxsearchstring, "wordhighlight");
							 },
							 error: function(data) {
								console.log("Ajax error");
							 }
						});
					}
					
					$.fn.highlight = function (str, className) {
						var regex = new RegExp(str, "gi");
						return this.each(function () {
							$(this).contents().filter(function() {
								return this.nodeType == 3 && regex.test(this.nodeValue);
							}).replaceWith(function() {
								return (this.nodeValue || "").replace(regex, function(match) {
									return "<span class=\"" + className + "\">" + match + "</span>";
								});
							});
						});
					};

					// at startup
					$("#ajax_loadindicator").hide();
					
					function animateLoading() {
					 var text = "...";
					 if($("#ajax_loadindicator").is(":visible")) {
						 $({count:0}).animate({count:text.length}, {
							 duration: 2000,
							 step: function() {
								 $(".dots").text( text.substring(0, Math.round(this.count)) );
							 },
							 complete: function() {
								 animateLoading();
							 }
						  });
						}
					 }

				}); // end ready
			</script>
			
			';
			
			return $qa_content;
		}
		
	};
	

/*
	Omit PHP closing tag to help avoid accidental output
*/