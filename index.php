<?php
    /**
     * Thực hiện lấy toàn bộ danh sách các quốc gia từ trang wiki về insert vào database
     * @author Betapcode <betapcode@gmail.com>
     * @version 1.0.0
     */

    // Thư viện để bóc tach html
    require_once "libs/simple_html_dom.php";
    // Thư viện làm việc với database
    require_once "libs/PDO.php";

    class CrawData {

        # config db tcoin
        private $server     = "127.0.0.1";
        private $username 	= "root";
        private $password 	= "123456";
        private $dbname 	= "wiki_country";
        private $params     = array();
        private $db         = null;

        // Định nghĩa link lấy danh sách country
        private $link_list_country      = "https://vi.wikipedia.org/wiki/Danh_s%C3%A1ch_c%C3%A1c_th%E1%BB%A7_%C4%91%C3%B4_qu%E1%BB%91c_gia";
        // Đường dẫn mặc định 1 link chi tiết 
        private $link_detail_country    = "https://vi.wikipedia.org/api/rest_v1/page/summary/";
        

        function __construct(){
           # config db
           $params["host"]     = $this->server;
           $params["user"]     = $this->username;
           $params["password"] = $this->password;
           $params["dbname"]   = $this->dbname;
           $this->db = new db($params);
        }

        function processData() {

            // Lấy dự liệu
            $html       = file_get_html($this->link_list_country);
            // Tìm dữ liệu trong thẻ table
            $table      = $html->find('table', 0);
            $rowData    = array();
            $dem = 0;
            // Step 1: duyệt danh sách các dòng và các cột lưu vào 1 mảng
            foreach($table->find('tr') as $row) {
                if ($dem > 0) {
                    $flight = array();
                    $temp = 0;
                    foreach($row->find('td') as $cell) {
                        $alink   = $cell->find("a");
                        foreach($alink as $r){
                            if (isset($r->attr['title']) && $temp == 1){
                                $flight[] = $r->attr['title'];
                            }
                        }
                        // push the cell's text to the array
                        $flight[] = $cell->plaintext;
                        $temp += 1;    
                    }
                    $rowData[] = $flight;
                }
                $dem += 1;
            }

            /**
             * Dữ liệu gồm 4 tham số: STT | Nước/Vùng lãnh thổ | Thủ đô | Ghi chú
             */
            // Step 2: duyệt mảng danh sách các quốc gia và xử lý
            foreach($rowData as $row) {
                
                // Loại bỏ ký tự &nbsp; 
                $country = str_replace("&#160;", "", $row[1]);
                // Chuyển dấu cách thành dấu gạch chân
                $country_convert = str_replace(" ", "_", trim($country));
                // Tạo link chi tiết 1 quốc gia
                $new_link_detail =  $this->link_detail_country.urlencode(trim($country_convert));
                // Lấy data chi tiết 1 quốc gia
                $returned_content = $this->get_data("$new_link_detail");
                // Decode json từ data trả về
                $homepage = json_decode($returned_content);

                $country_name   = trim(str_replace("&#160;", "", $row[2]));
                $extract        = $homepage->extract;
                $extract_html   = $homepage->extract_html;
                $thumbnail      = json_encode($homepage->thumbnail);
                $originalimage  = json_encode($homepage->originalimage);
                $timestamp      = $homepage->timestamp;
                $created        = time();

                $sql_insert = sprintf("INSERT INTO country(cate_id, country_name, extract, extract_html, thumbnail, originalimage, `timestamp`, created) VALUES(%d, '%s', '%s', '%s', '%s', '%s', '%s', %d)",
                    mysql_real_escape_string(0),
                    mysql_real_escape_string($country_name),
                    mysql_real_escape_string($extract),
                    mysql_real_escape_string($extract_html),
                    mysql_real_escape_string($thumbnail),
                    mysql_real_escape_string($originalimage),
                    mysql_real_escape_string($timestamp),
                    mysql_real_escape_string($created)
                );

                $runQuery = $this->db->execute($sql_insert);

                echo "<br/> + STT: ". $row[0] . " | Quốc Gia: ". $country_name . " => runQuery: ";
                print_r($runQuery);
            
            }


        }

        /* gets the data from a URL */
        function get_data($url) {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec($ch);
            curl_close($ch);
            return $data;
        }

    }// end class

    // Run class action
    $crawData = new CrawData();
    $crawData->processData();

?>