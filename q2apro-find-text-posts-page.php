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
			$level = qa_get_logged_in_level();
			
			if($level < QA_USER_LEVEL_ADMIN) 
			{
				$qa_content = qa_content_prepare();
				$qa_content['custom'] = '<p>'.qa_lang('q2apro_find_text_posts_lang/not_allowed').'</p>';
				return $qa_content;
			}
			

			// AJAX post: we received post data, so it should be the ajax call
			$transferString = qa_post_text('ajaxdata');
			
			if(!empty($transferString)) 
			{
				
				$newdata = json_decode($transferString, true);
				$newdata = str_replace('&quot;', '"', $newdata); // see stackoverflow.com/questions/3110487/

				$mode = $newdata['ajax_mode'];
				$searchstring = $newdata['ajaxsearchstring'];
				$string_search = $newdata['ajaxstring_search'];
				$string_replace = $newdata['ajaxstring_replace'];

				if(empty($searchstring) && empty($string_search)) 
				{
					echo 'data missing';
					return;
				}
				
				if($mode=='search')
				{
					$ajaxreturn = '';
					
					$questionid = null;
					$urlparams = null;
					$postanchor = null;
					$qTitle = null;
					$postcontent = '';
					$posttype_label = '';
					
					// get type, title and content of post
					$postdatas = qa_db_read_all_assoc(
									qa_db_query_sub('SELECT postid, type, created, title, content, userid FROM `^posts` 
													WHERE `content` LIKE #
													OR `title` LIKE #
													', '%'.$searchstring.'%', '%'.$searchstring.'%')
								 );

					foreach($postdatas as $postdata) 
					{
					
						$posttype = $postdata['type'];
						if(empty($posttype)) {
							$ajaxreturn .= '<p>'.qa_lang('q2apro_find_text_posts_lang/error2').'</p>';
							echo json_encode(array($ajaxreturn));
							return;
						}
						// save content for output
						$postcontent = $postdata['content'];
						
						if($posttype=='Q' || $posttype=='Q_HIDDEN')
						{
							$qTitle = $postdata['title'];
							$questionid = $postdata['postid'];
							$posttype_label = qa_lang('q2apro_find_text_posts_lang/question');
						}
						else 
						{
							// no question, post must be A or C
							if($posttype=='A' || $posttype=='A_HIDDEN') 
							{
								$posttype_label = qa_lang('q2apro_find_text_posts_lang/answer');
							}
							else if($posttype=='C' || $posttype=='C_HIDDEN') 
							{
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
							if($parenttype=='Q' || $parenttype=='Q_HIDDEN') 
							{
								// question
								$questionid = $parentid;
								$qTitle = $parentpost['title'];
							}
							else if($parenttype=='A' || $parenttype=='A_HIDDEN') 
							{
								// answer, we need to query again to receive the question id
								$question = qa_db_read_one_assoc( 
													qa_db_query_sub('SELECT postid, title FROM `^posts` 
																		WHERE `postid` = # 
																		AND `type` = "Q"
																		LIMIT 1', $parentpost['parentid']) );
								$questionid = $question['postid'];
								$qTitle = $question['title'];
							}
							else 
							{
								// content output error
								$ajaxreturn .= '<p>'.qa_lang('q2apro_find_text_posts_lang/error1').'</p>';
								echo json_encode(array($ajaxreturn));
								return;
							}
						} // end A or C
						
						// if everything is alright, we have the questionid and return the data
						if(isset($questionid)) 
						{
							if(empty($qTitle)) 
							{
								$qTitle = '';									
							}
							
							// function qa_path($request, $params=null, $rooturl=null, $neaturls=null, $anchor=null)
							$posturl = qa_path(qa_q_request($questionid, $qTitle), $urlparams, qa_opt('site_url'), null, $postanchor);
							// ?show=10081#a10081
							$postlink = '<a target="_blank" href="'.$posturl.'">'.$qTitle.'</a>';

							$postcreator = qa_lang('q2apro_find_text_posts_lang/anonymous');
							$postcreatorlink = $postcreator;
							if(isset($postdata['userid'])) 
							{
								$postcreator = qa_db_read_one_value(qa_db_query_sub('SELECT handle FROM ^users WHERE `userid` = #', $postdata['userid']), true);
								$postcreatorlink = '<a href="'.qa_path('user').'/'.$postcreator.'">'.$postcreator.'</a>';
							}
							
							$postdate = implode('', qa_when_to_html(strtotime($postdata['created']), qa_opt('show_full_date_days')));
							
							$ajaxreturn .= '
										<div class="ajaxresult_item">
											<div class="linkresult">
											<p>
												<span>'.$posttype_label.':</span> 
												<span>'.$postlink.'</span>
											</p>
											<p>
												<span>'.qa_lang('q2apro_find_text_posts_lang/postcreator').'</span> 
												<span>'.$postcreatorlink.'</span>
											</p>
											<p style="display:none;">
												<span>'.qa_lang('q2apro_find_text_posts_lang/posturl').'</span> 
												<span>'.$posturl.'</span>
											</p>
											<p>
												<span>'.qa_lang('q2apro_find_text_posts_lang/postdate').'</span> 
												<span>'.$postdate.'</span>
											</p>
											</div>
											<p id="postcontent_head">'.qa_lang('q2apro_find_text_posts_lang/postcontent').'</p> 
											<div id="postcontent">'.$postcontent.'</div>
										</div> <!-- ajaxresult_item -->
										
										<div class="resultdivider"></div>
							';
						}
						else 
						{
							// content output error
							$ajaxreturn .= '<p>'.qa_lang('q2apro_find_text_posts_lang/error2').'</p>';
							echo json_encode(array($ajaxreturn));
							return;
						}
						
					} // end foreach($postdatas
					
					$ajaxreturn = '<p id="ajaxresultcount">Results: '.count($postdatas).'</p>'.$ajaxreturn;
					echo json_encode(array($ajaxreturn));
					return;
				}
				else if($mode=='replace')
				{
					if(!empty($string_search))
					{
						if(empty($string_replace))
						{
							$string_replace = '';
						}
						
						// replace in database
						qa_db_query_sub('UPDATE `^posts` SET `content` = REPLACE(content, $, $)', 
										 $string_search, $string_replace);
						
						$ajaxreturn = '
							<p class="qa-success" id="ajaxresultcount">
								String "'.$string_search.'" replaced with "'.$string_replace.'"
							</p>
						';
						echo json_encode(array($ajaxreturn));
						return;
					}
				}
				
				return;
				
			} // end AJAX return


			// start content
			$qa_content = qa_content_prepare();

			// page title
			$qa_content['title'] = qa_lang('q2apro_find_text_posts_lang/page_title'); 

			// some CSS styling
			$qa_content['custom'] = '
			<style>
				.indiv { 
					display:inline-block;
					border-left:10px solid #ABF;
					padding:5px 10px; 
					vertical-align:top;
				}
				.indiv:nth-child(2) { 
					margin:20px 50px 0 5px;
				}
				.indiv:nth-child(3) { 
					margin:20px 0 0 0;
					border-left:10px solid #FAA;
				}
				.qa-main h1 { 
					margin-bottom:40px; 
				}
				input#searchstring_input,
				input#replaceinput_search, 
				input#replaceinput_replace { 
					display:inline-block;
					width:200px; 
					border:1px solid #EEE; 
					padding:3px; 
					margin-bottom:15px;
				}
				#ajaxresultcount {
					margin:20px 0 0 25px;
					font-size:18px;
				}
				#ajaxresult {
				}
				.ajaxresult_item {
					display:block;
					padding:10px;
					margin:70px 25px;
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
			$qa_content['custom'] .= '
										<div class="indiv">
											<h3>
												'.qa_lang('q2apro_find_text_posts_lang/headline_search').'
											</h3>
											<input name="searchstring_input" id="searchstring_input" type="text" placeholder="'.qa_lang('q2apro_find_text_posts_lang/input_placeholder').'" autofocus>
											<div class="ajax_loadindicator ajax_loadindicator_search">'.qa_lang('q2apro_find_text_posts_lang/searching').' <span class="dots"></span> </div>
											<br />
											<span class="btnblue submitbtn" id="submitbtn_search">'.qa_lang('q2apro_find_text_posts_lang/submitbutton').'</span>
										 </div>
										 
										<div class="indiv">
											<h3>
												'.qa_lang('q2apro_find_text_posts_lang/headline_replace').'
											</h3>
											<input name="replaceinput_search" id="replaceinput_search" type="text" title="'.qa_lang('q2apro_find_text_posts_lang/input_search_tooltip').'" placeholder="'.qa_lang('q2apro_find_text_posts_lang/input_search_placeholder').'" autofocus>
											<input name="replaceinput_replace" id="replaceinput_replace" type="text" placeholder="'.qa_lang('q2apro_find_text_posts_lang/input_replace_placeholder').'" autofocus>
											<div class="ajax_loadindicator ajax_loadindicator_replace">'.qa_lang('q2apro_find_text_posts_lang/replacing').' <span class="dots"></span> </div>
											<br />
											<span class="btnblue submitbtn"  id="submitbtn_replace">'.qa_lang('q2apro_find_text_posts_lang/submitbutton_replace').'</span>
										 </div>
									';
									
			$qa_content['custom'] .= '<div id="ajaxresult"></div>';
			
			$qa_content['custom'] .= '
			<script type="text/javascript">
				$(document).ready(function()
				{
					$("#submitbtn_search").click( function() 
					{
						doAjaxPost("search");
					});
					
					$("#submitbtn_replace").click( function() 
					{
						doAjaxPost("replace");
					});
					
					$("#searchstring_input").keyup(function(e) 
					{
						// if enter key
						if(e.which == 13) 
						{
							doAjaxPost("search");
						}
					});

					$("#replaceinput_search, #replaceinput_replace").keyup(function(e) 
					{
						// if enter key
						if(e.which == 13) 
						{
							doAjaxPost("replace");
						}
					});

					function doAjaxPost(mode) 
					{
						// get searchstring from input
						var ajaxsearchstring = $("#searchstring_input").val(); 
						var ajaxstring_search = $("#replaceinput_search").val();
						var ajaxstring_replace = $("#replaceinput_replace").val();
						var ajax_mode = mode; 
						
						if(ajaxsearchstring=="" && ajaxstring_search=="")
						{
							return;
						}
						
						if(mode=="search")
						{
							$(".ajax_loadindicator_search").show();							
						}
						else
						{
							$(".ajax_loadindicator_replace").show();
						}
						
						animateLoading();
						
						var dataArray = {
							ajax_mode: ajax_mode, 
							ajaxsearchstring: ajaxsearchstring,
							ajaxstring_search: ajaxstring_search,
							ajaxstring_replace: ajaxstring_replace
						};
						
						var senddata = JSON.stringify(dataArray);
						console.log("sending: "+senddata);
						
						// send ajax
						$.ajax({
							 type: "POST",
							 url: "'.qa_self_html().'",
							 data: { ajaxdata: senddata },
							 dataType:"json",
							 cache: false,
							 success: function(data)
							 {
								console.log("got server data");
								// console.log("server returned:"+data);
								$(".ajax_loadindicator").hide();
								// output result in DIV
								$("#ajaxresult").html( data );
								$("#ajaxresult *").highlight(ajaxsearchstring, "wordhighlight");
							 },
							 error: function(data)
							 {
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
					$(".ajax_loadindicator").hide();
					
					function animateLoading() 
					{
						var text = "...";
						if($(".ajax_loadindicator").is(":visible")) 
						{
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