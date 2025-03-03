//** 
* Note: I used $this->logPost() without passing the product_id. You could easily extend it by passing the product_id to logPost() so you can see which product was posted. For clarity, we left product_id = 0 in the example. Consider updating: 
* $this->logPost('instagram', $log_status, $log_msg, $product_id);
*/

<?php
class ModelExtensionModuleAdvancedAutopost extends Model {
    public function createAutopostLogTable() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "advanced_autopost_log` (
              `log_id` INT(11) NOT NULL AUTO_INCREMENT,
              `product_id` INT(11) NOT NULL,
              `platform` VARCHAR(50) NOT NULL,
              `status` ENUM('success','error') NOT NULL,
              `message` TEXT DEFAULT NULL,
              `date_added` DATETIME NOT NULL,
              PRIMARY KEY (`log_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    public function getLogs() {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "advanced_autopost_log` ORDER BY `log_id` DESC");
        return $query->rows;
    }

    public function clearLogs() {
        $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "advanced_autopost_log`");
    }

    /**
     * Called by the events after add/edit product or manual post button.
     */
    public function eventPostProduct(&$route, &$args, &$output) {
        // Grab auto-post setting
        $status = $this->config->get('module_advanced_autopost_status');
        $auto_mode = $this->config->get('module_advanced_autopost_auto_mode');

        if (!$status || !$auto_mode) {
            // Module disabled or auto mode off, do nothing
            return;
        }

        // Determine product_id based on route
        if ($route == 'admin/model/catalog/product/addProduct/after') {
            $product_id = (int)$output; // returned product_id
        } elseif ($route == 'admin/model/catalog/product/editProduct/after') {
            $product_id = (int)$args[0]; // first arg is product_id
        } else {
            return;
        }

        if ($product_id > 0) {
            $this->postProductToSocial($product_id);
        }
    }

    /**
     * Main function to post product info to social platforms
     */
    public function postProductToSocial($product_id) {
        $errors = array();
        $error_count = 0;

        // 1. Load product data
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($product_id);
        if (!$product_info) {
            return array('error_count' => 1, 'errors' => ['Product not found']);
        }

        $product_name = $product_info['name'];
        // Build front-end URL
        $product_url = HTTPS_CATALOG . 'index.php?route=product/product&product_id=' . $product_id;

        // Load all product images if multiple
        $post_multiple = $this->config->get('module_advanced_autopost_post_images');
        $images = array();
        if ($post_multiple) {
            $product_images = $this->model_catalog_product->getProductImages($product_id);
            // Main image too
            if ($product_info['image']) {
                array_unshift($product_images, array('image' => $product_info['image']));
            }
            foreach ($product_images as $img) {
                $images[] = HTTPS_CATALOG . 'image/' . $img['image'];
            }
        } else {
            // Just main image
            if ($product_info['image']) {
                $images[] = HTTPS_CATALOG . 'image/' . $product_info['image'];
            }
        }

        // 2. Build post message
        $template = $this->config->get('module_advanced_autopost_post_template');
        if (!$template) {
            $template = 'New product: {product_name} {product_url}';
        }
        $store_name = $this->config->get('config_name');
        $message = str_replace(
            array('{product_name}', '{product_url}', '{store_name}'),
            array($product_name, $product_url, $store_name),
            $template
        );

        // 3. Post to Facebook
        $fb_token = $this->config->get('module_advanced_autopost_facebook_token');
        if ($fb_token) {
            $fb_result = $this->postToFacebook($fb_token, $message, $images);

            if ($fb_result['status'] == 'error') {
                $error_count++;
                $errors[] = 'Facebook error: ' . $fb_result['message'];
            }
        }

        // 4. Post to Instagram
        $ig_token = $this->config->get('module_advanced_autopost_instagram_token');
        $ig_user_id = $this->config->get('module_advanced_autopost_instagram_user_id');
        if ($ig_token && $ig_user_id) {
            $ig_result = $this->postToInstagram($ig_user_id, $ig_token, $message, $images);

            if ($ig_result['status'] == 'error') {
                $error_count++;
                $errors[] = 'Instagram error: ' . $ig_result['message'];
            }
        }

        // Return combined result
        return array(
            'error_count' => $error_count,
            'errors'      => $errors
        );
    }

    /**
     * Simple Facebook multi-photo post logic.
     *   If multiple images, we create multiple photo posts in a single "batch"
     *   or you might do a single post with multiple images (Facebook has a "child_attachments" approach, but more complex).
     */
    private function postToFacebook($page_access_token, $message, $images) {
        $log_status = 'success';
        $log_msg = 'Posted successfully';

        // If you want a single photo post only, simplify to just images[0].
        // For multiple images in one feed post, you must use 'attached_media' approach. We'll do a simplified approach: multiple separate posts.

        try {
            // For each image, create a photo post with the same message
            foreach ($images as $index => $image_url) {
                // Graph API endpoint for photo
                $url = 'https://graph.facebook.com/v16.0/me/photos';

                $postData = array(
                    'url'          => $image_url,
                    'message'      => ($index == 0) ? $message : '', // only put the message in first image post if you want
                    'access_token' => $page_access_token
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                $error  = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new Exception($error);
                }

                $json = json_decode($result, true);
                if (isset($json['error'])) {
                    throw new Exception($json['error']['message']);
                }
            }
        } catch (Exception $e) {
            $log_status = 'error';
            $log_msg = $e->getMessage();
        }

        // Log the result
        $this->logPost('facebook', $log_status, $log_msg);

        return array('status' => $log_status, 'message' => $log_msg);
    }

    /**
     * Instagram multi-image post using "carousel" approach:
     *  1) Create media object for each image with is_carousel_item = true
     *  2) Collect creation_ids
     *  3) Create a single container with media_type=CAROUSEL
     */
    private function postToInstagram($ig_user_id, $ig_access_token, $message, $images) {
        $log_status = 'success';
        $log_msg = 'Posted successfully';

        try {
            if (count($images) > 1) {
                // Carousel approach
                $creation_ids = array();
                foreach ($images as $image_url) {
                    $media_url = 'https://graph.facebook.com/v16.0/' . $ig_user_id . '/media';
                    $postData = array(
                        'image_url'        => $image_url,
                        'is_carousel_item' => 'true',
                        'access_token'     => $ig_access_token
                    );

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $media_url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($ch);
                    $error  = curl_error($ch);
                    curl_close($ch);

                    if ($error) {
                        throw new Exception($error);
                    }
                    $json = json_decode($result, true);
                    if (!isset($json['id'])) {
                        throw new Exception("Error creating carousel media item: " . $result);
                    }
                    $creation_ids[] = $json['id'];
                }

                // Now create the carousel container with a single caption
                $carouselData = array(
                    'media_type'   => 'CAROUSEL',
                    'children'     => implode(',', $creation_ids),
                    'caption'      => $message,
                    'access_token' => $ig_access_token
                );
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v16.0/' . $ig_user_id . '/media');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $carouselData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $cResult = curl_exec($ch);
                $cError  = curl_error($ch);
                curl_close($ch);

                if ($cError) {
                    throw new Exception($cError);
                }
                $cJson = json_decode($cResult, true);
                if (!isset($cJson['id'])) {
                    throw new Exception("Error creating carousel container: " . $cResult);
                }
                $carousel_id = $cJson['id'];

                // Finally publish the carousel
                $publishData = array(
                    'creation_id'  => $carousel_id,
                    'access_token' => $ig_access_token
                );
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/v16.0/' . $ig_user_id . '/media_publish');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $publishData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $publish_result = curl_exec($ch);
                $publish_error  = curl_error($ch);
                curl_close($ch);

                if ($publish_error) {
                    throw new Exception($publish_error);
                }
                $pJson = json_decode($publish_result, true);
                if (isset($pJson['error'])) {
                    throw new Exception($pJson['error']['message']);
                }

            } else {
                // Single image
                $single_image = $images ? $images[0] : '';
                // Step 1: Create media
                $createData = array(
                    'image_url'    => $single_image,
                    'caption'      => $message,
                    'access_token' => $ig_access_token
                );
                $ch = curl_init('https://graph.facebook.com/v16.0/' . $ig_user_id . '/media');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $createData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                $error  = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new Exception($error);
                }
                $json = json_decode($result, true);
                if (!isset($json['id'])) {
                    throw new Exception("Error creating media: " . $result);
                }
                $creation_id = $json['id'];

                // Step 2: Publish
                $publishData = array(
                    'creation_id'  => $creation_id,
                    'access_token' => $ig_access_token
                );
                $ch = curl_init('https://graph.facebook.com/v16.0/' . $ig_user_id . '/media_publish');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $publishData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $pResult = curl_exec($ch);
                $pError  = curl_error($ch);
                curl_close($ch);

                if ($pError) {
                    throw new Exception($pError);
                }
                $pJson = json_decode($pResult, true);
                if (isset($pJson['error'])) {
                    throw new Exception($pJson['error']['message']);
                }
            }
        } catch (Exception $e) {
            $log_status = 'error';
            $log_msg = $e->getMessage();
        }

        // Log the result
        $this->logPost('instagram', $log_status, $log_msg);

        return array('status' => $log_status, 'message' => $log_msg);
    }

    private function logPost($platform, $status, $message) {
        // store minimal info (you may also store product_id if needed here or pass it)
        // in real usage, pass product_id
        $sql = "INSERT INTO `" . DB_PREFIX . "advanced_autopost_log`
                SET `product_id` = '0',
                    `platform`   = '" . $this->db->escape($platform) . "',
                    `status`     = '" . $this->db->escape($status) . "',
                    `message`    = '" . $this->db->escape($message) . "',
                    `date_added` = NOW()";
        $this->db->query($sql);
    }
}
