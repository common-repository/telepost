<?php
    
    function tpost_sgs_getDOMinnerHTML(DOMNode $element , $replaceDivs) 
    { 
        $innerHTML = ""; 
        $children  = $element->childNodes;
    
        foreach ($children as $child) 
        { 
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
    
        return $replaceDivs ? preg_replace('/\<[\/]{0,1}div[^\>]*\>/i', '', $innerHTML) : $innerHTML;
    } 
    
    function tpost_sgs_upload_image($url, $post_id) {
        
        include_once( ABSPATH . 'wp-admin/includes/image.php' );
        $imageurl = $url;
        $imagetype = end(explode('/', getimagesize($imageurl)['mime']));
        $uniq_name = date('dmY').'-'.$post_id; 
        $filename = $uniq_name.'.jpg';

        $uploaddir = wp_upload_dir();
        $uploadfile = $uploaddir['path'] . '/' . $filename;
        $contents= file_get_contents($imageurl);
        $savefile = fopen($uploadfile, 'w');
        fwrite($savefile, $contents);
        fclose($savefile);

        $wp_filetype = wp_check_filetype(basename($filename), null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => $filename,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $uploadfile ,$post_id);
        $imagenew = get_post( $attach_id );
        $fullsizepath = get_attached_file( $imagenew->ID );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $fullsizepath );
        wp_update_attachment_metadata( $attach_id, $attach_data ); 
        // And finally assign featured image to post
        $thumbnail = set_post_thumbnail($post_id, $attach_id);
    }

    function tpost_sgs_insertWpPost($content, $catId , $imgUrl)
    {
        $theShortTitle = "";// get first 8 word for title
        $words = explode(" ",strip_tags(  $content ));
        $cnt = 0;
        foreach ($words as $word){
            if($cnt > 8)
                break;
            $cnt++;
            $theShortTitle .= $word . " ";
        }
        // Create post object
        $my_post = array();
        $my_post['post_title']    = $theShortTitle;
        $my_post['post_content']  = $content;
        $my_post['post_status']   = 'publish';
        $my_post['post_author']   = 1;
        $my_post['post_category'] =[$catId];
        // Insert the post into the database
        $savedPostId = wp_insert_post( $my_post );
        //error_log("postId:".$savedPostId." ,".$imgUrl);
        if($imgUrl!="")
            tpost_sgs_upload_image($imgUrl , $savedPostId );
    }

    function tpost_sgs_getDateTime(){
        return date('m/d/Y h:i:s a', time());
    }
    

    function tpost_sgs_scrapeTelegram(){
        try{
                                
                        update_option('last_tg_cron_executed' , tpost_sgs_getDateTime() );
                
                        $telegram_fetch_settings_options = get_option( 'telegram_fetch_settings_option_name' ); // Array of All Options
                        $sourceChannels = $telegram_fetch_settings_options['source_telegram_channel_usernames_comma_seperated_0']; // Source Telegram Channel usernames comma seperated
                        $sourceCatIds = $telegram_fetch_settings_options['category_ids_comma_seperated_for_each_channel_set_one_id_1']; // Category Ids comma seperated(For each channel set one id)
                        error_log('sgs cron executed');
                        if(! isset( $sourceChannels))
                        {
                            error_log('Please specify the source Telegram channels in Settings page');
                        }
                        $channelObjs =  explode(",", $sourceChannels);
                        $catIds = explode(",", $sourceCatIds);
                        $index = 0;
                        foreach($channelObjs as $obj )
                        {
                            
                            $username = explode(";",$obj)[0];
                            $catId = $catIds[$index];
                            error_log('fetch:'.$username.':'.$catId);
                            //
                            $tgResponse = wp_remote_get("https://t.me/s/".$username);//"file:///C:/Users/sadeg/Desktop/mashhad.htm"
                            $htmlString = wp_remote_retrieve_body($tgResponse);
                            //add this line to suppress any warnings
                            libxml_use_internal_errors(true);
                            $doc = new DOMDocument();
                            $doc->loadHTML($htmlString);
                           
                            $xpath = new DOMXPath($doc);
                            //error_log($htmlString);
                            
                            $posts = $xpath->query('//div[@class="tgme_widget_message text_not_supported_wrap js-widget_message"]');
                            $lastPostIdKey =  'last_PostId_'.$username;
                            if(get_option($lastPostIdKey))
                                    $lastCrawled_PostId = intval( get_option($lastPostIdKey)) ; 
                                else
                                    $lastCrawled_PostId = 0;
                            
                            error_log("posts len:".$posts->length);
                            for ($i = 0; $i < $posts->length ; $i++) {
                                $postId = explode("/", $posts->item($i)->getAttribute("data-post"))[1];
                                if(intval($postId) <= $lastCrawled_PostId){
                                    error_log("skipped post :".$postId);
                                    break;
                                }
                                //$imgPostStyle = $xpath->evaluate('string(./div[@class="tgme_widget_message_bubble"]/a/@style)', $posts->item($i));
                                $txtElements = $xpath->query('.//*/div[@class="tgme_widget_message_text js-message_text"]', $posts->item($i))[0]; 
                                //$photoElement = $xpath->query('.//*[@class="tgme_widget_message_photo_wrap"]', $posts->item($i))[0]; 
                                
                                $photoPostHtml = tpost_sgs_getDOMinnerHTML($posts->item($i) , false);
                                $imgUrls = [];
                                //print($photoPostHtml);
                                preg_match("/url\(\'https:\/\/(www\.)?[^\"]*\.jpg\'/im",$photoPostHtml , $imgUrls);
                                $videoUrls = [];
                                preg_match("/src=\"https:\/\/(www\.)?[^\"]*\.mp4/im",$photoPostHtml , $videoUrls);
                                
                                if(count($videoUrls) == 0 && $txtElements != null){//skip video post
                                    $postHtml = tpost_sgs_getDOMinnerHTML($txtElements , true);
                                    
                                    tpost_sgs_insertWpPost($postHtml , $catId , count( $imgUrls) == 0 ? "" : str_replace("'","", str_replace("url(","", $imgUrls[0])) );
                                    
                                    update_option($lastPostIdKey ,$postId);
                                }
                            }
                            $index++;
                        }
                        update_option('last_tg_cron_executed' , tpost_sgs_getDateTime()." | status:ok" );
            
        }
        catch (\Exception $ex) {
            error_log( $ex->getMessage());
            update_option('last_tg_cron_executed' , tpost_sgs_getDateTime()." | status:".$ex->getMessage() );
        }
        catch (\Throwable $ex) {
            error_log( $ex->getMessage());
            update_option('last_tg_cron_executed' , tpost_sgs_getDateTime()." | status:".$ex->getMessage() );
        }
    }    

    //tpost_sgs_scrapeTelegram();
?>