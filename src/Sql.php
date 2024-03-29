<?php namespace blakepro\Template; use PDO;

class Sql extends Utilities{

  public function __construct($attr = []){
    $this->database_host = $this->key('host', $attr);
    $this->database_name = $this->key('name', $attr);
    $this->database_user = $this->key('user', $attr);
    $this->database_password = $this->key('password', $attr);
    $this->encryption_key = $this->key('encryption_key', $attr);
  }

  public function criteria(){
    return ['=', ' !=', 'LIKE', 'LIKE %...%', 'NOT LIKE', 'REGEXP', 'NOT REGEXP', 'IN (...)', 'NOT IN (...)', 'IS NULL', 'IS NOT NULL' , 'BETWEEN', 'NOT BETWEEN', '>', '>=', '<', '<='];
  }

  public function query($args){
    $type = $this->key('type', $args);
    if($type == '')$type = 'select';
    switch ($type){
      case 'select': return $this->select($args); break;
      case 'insert': return $this->insert($args); break;
      case 'update': return $this->update($args); break;
      case 'delete': return $this->delete($args); break;
      default: return [];
    }
  }

  public function fetch($array){
    return $this->key('fetch', $array);
  }

  public function message($array){
    return $this->key('message', $array);
  }

  public function get_query($array){
    return $this->key('sql', $array);
  }

  public function get_id_insert($array){
    if($this->is_content($array))
    return $this->key('insert', $this->key('fetch', $this->key(key($array), $array)));
  }

  public function db(){
    $connect = '';
    try{
      $connect .= "mysql:host={$this->database_host};";
      if($this->database_name != '')$connect .= "mysql:dbname={$this->database_name};";
      $connect .= 'charset=UTF8;';
      $db = new PDO($connect, $this->database_user, $this->database_password);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
      return $db;
    }catch(PDOException $e) {
      echo $e;
      exit;
    }
  }

  public function where($string, $delimiter = ','){
    $array = $array_where = [];
    $where = $where_str = '';
    if($string != '' && $delimiter != ''){
      if(is_array($string))$array = $string;
      else{
        $string = str_replace("'",'',$string);
        $array = explode($delimiter, $string);
      }
      if($this->is_content($array)){
        foreach($array as $k => $v){
          if(is_array($v)){
            if($k == '*')$k = '';
            $array_where[] = $k;
            $where .= '?,';
            $where_str .= "'$k',";
          }else{
            if($v == '*')$v = '';
            $array_where[] = $v;
            $where .= '?,';
            $where_str .= "'$v',";
          }
        }
        $where = $this->remove_string($where, 1);
        $where_str = $this->remove_string($where_str, 1);
      }
    }
    return ['array' => $array_where, 'where_str' => $where_str, 'where' => $where];
  }

  public function sql($sql, $params = [], $fetch = TRUE, $return_id = FALSE, $exec_simple = FALSE){
  	$data_fetch = [];
  	$message = '';
  	$state = FALSE;
  	$sql_send = $sql;
  	if($sql != ''){
      try{
        $db = $this->db();
        if($exec_simple){
          $db->exec($sql);
        }else{
          $sql = $db->prepare($sql);
          try{
            @$sql->execute($params);
            $message = $sql->errorInfo();
            if($this->key(2, $message) == ''){
              $state = TRUE;
              if($return_id)$data_fetch['insert'] = $db->lastInsertId(); //@$db->lastInsertId();
              elseif($fetch)$data_fetch = $sql->fetchALL(PDO::FETCH_ASSOC); //@$sql->fetchALL(PDO::FETCH_ASSOC);
            }
          }catch(PDOException $exception){
            $message = $exception->getMessage();
          }
        }
        $db = null;
      }catch(PDOException $exception) {
        $message = $exception->getMessage();
      }
    }
    return ['state' => $state, 'message' => $message, 'fetch' => $data_fetch, 'sql' => $this->query_print($sql_send, $params)];
  }

  private function update($data){
    $return = [];
    $set = $this->key('set', $data);
    $array_criteria_where = $this->criteria();
    if($this->is_content($set)){
      $where_criteria = $this->key('where', $data);

      $table = [];
      foreach($set as $name_table => $arr_set)$table[] = $name_table;

      $arr_sql_desc = $this->description($table);
      $arr_not_null = $this->key('not_null', $arr_sql_desc);

      foreach($set as $name_table => $arr_set){
        $str_empty = $set_table = $where_table = '';
        $params = [];

        //-------- SET --------
        if($this->is_content($arr_set)){
          foreach($arr_set as $field_name => $value){
            if(isset($arr_not_null[$name_table][$field_name]) && $value == ''){
              $str_empty .= "<li>{$arr_not_null[$name_table][$field_name]}</li>";
            }
            if($value == NULL){
              $set_table .= " $field_name = NULL,";
            }else if(is_string($value)){
              $set_table .= " $field_name = ?,";
              $params[] = "{$value}";
            }
          }
        }

        //-------- WHERE --------
        if(array_key_exists($name_table, $where_criteria) && !empty($where_criteria[$name_table]) && is_array($where_criteria[$name_table])){

          foreach($where_criteria[$name_table] as $field_name => $array_criteria_data){
            //WHERE UPDATE VERSION
            $arr_table_where = $this->get_table_where($array_criteria_where, $where_criteria, $name_table, $field_name);
            $where_table .= $this->key('where', $arr_table_where);
            $all_params = $this->key('params', $arr_table_where);
            if($this->is_content($all_params)){foreach($all_params as $kp => $vparam)$params[] = $vparam;}
            //WHERE UPDATE VERSION
          }
        }

        $set_table = trim($this->remove_string($set_table, 1));

        $where_table = trim(substr(trim($where_table), 3));
        if($where_table != '')$where_table = "WHERE {$where_table}";

        $sql = "UPDATE {$name_table} SET $set_table $where_table";
        $return[$name_table]['sql'] = $this->query_print($sql, $params);
        $return[$name_table]['state'] = FALSE;

        if($str_empty != '')$return[$name_table]['message'] = "Fill: <ul>{$str_empty}</ul>";
        else{
          if($this->is_content($params) && $set_table != '' && $where_table != ''){
            $return[$name_table] = $this->sql($sql, $params, FALSE);
          }else{
            $return[$name_table]['state'] = FALSE;
            $return[$name_table]['message'] = 'Not empty where allowed';
          }
        }
      }
    }
    return $return;
  }

  private function select($data){
  	$arr_sql_data = $arr_sql_desc = [];
    $db = $this->get_database_name($this->key('db', $data));
    $table = $this->key('table', $data);
  	$options = $this->key('options', $data);
    $array_criteria_where = $this->criteria();
    if($this->is_content($table)){

      $arr_sql_field_key = [];
      $select = $this->key('select', $data, []);
      $debug = $this->key('debug', $data, FALSE);
      $where_criteria = $this->key('where', $data);
      $limit = $this->key('limit', $data);
      $on = $this->key('on', $data);
      $at = $this->key('at', $data);
      $group = $this->key('group', $data);
      $group_on = $this->key('group_on', $data);
      $order = $this->key('order', $data);
      $rand = $this->key('rand', $data, FALSE);

      //ADD NEW WAY TO PASS LIMIT OFFSET
      if(is_numeric($limit) && $limit > 0)$limit = "LIMIT $limit";
      else{
        if(is_array($limit)){
          $no_record = $this->key(0, $limit);
          $no_limit = $this->key(1, $limit);
          if(is_numeric($no_record) && is_numeric($no_limit))$limit = "LIMIT {$no_record},{$no_limit}";
          else $limit = '';
        }else $limit = '';
      }

      $arr_sql_desc = $this->description($table, $db);

      $arr_sql_fields = $this->key('fields', $arr_sql_desc);
  		$arr_sql_key = $this->key('key', $arr_sql_desc);
      $arr_data_rows = $all_params = [];
      $sql_join = $rows = $keys = $val_join = $group_by = $order_by = '';

  		//KEYS (ON)
  		if($this->is_content($on)){

  			foreach($on as $key_name => $arr_on){
					$str_key = '';
					foreach($arr_on as $possible_table => $key_table){
						if(in_array($key_table, $table) && $key_name != '' && $key_table != ''){
							$str_key .= "{$key_table}.{$key_name} = ";
            //COMPOUND NAME
						}elseif(in_array($possible_table, $table) && $possible_table != '' && $possible_table != ''  && $key_table != ''){
              $str_key .= "{$possible_table}.{$key_table} = ";
            }
					}
					if($str_key != ''){
						$str_key = $this->remove_string($str_key, 2);
						$keys .= "{$str_key} AND ";
					}
  			}
        //KEY
        if($keys != ''){
          $keys = $this->remove_string($keys, 4);
          if(!$this->in_string('=', $keys))$keys = '';
          $keys = "ON ($keys)";
        }
    	}

      //GROUP
  		if($this->is_content($group)){
				foreach($group as $key_table => $arrtable){
					$str_group = '';
					foreach($arrtable as $key_name => $group_name){
  					if(in_array($key_table, $table) && $key_table != '' && $group_name != ''){
  						$str_group .= "{$key_table}__{$group_name}, ";
  					}
					}
				}
  			$group_by = $this->remove_string($str_group, 2);
  			if($group_by != '')$group_by = "GROUP BY $group_by";
      }else{
    		//GROUP ON
    		if($this->is_content($group_on)){
    			foreach($group_on as $no_on => $arr_on){
    				foreach($arr_on as $key_name => $arrtable){
    					$str_key = '';
    					foreach($arrtable as $notable => $key_table){
    						if(in_array($key_table, $table) && $key_name != '' && $key_table != ''){
    							$str_key .= "{$key_table}.{$key_name} = ";
    						}
    					}
    					if($str_key != ''){
    						$str_key = $this->remove_string($str_key, 2);
    						$group_by .= "{$str_key} AND ";
    					}
    				}
    			}
    			$group_by = $this->remove_string($group_by, 5);
    			if($group_by != '')$group_by = "GROUP BY $group_by";
    		}
      }

      //ORDER
      if($rand)$order_by = "ORDER BY RAND()";
      else{
        if($this->is_content($order)){
  				foreach($order as $key_table => $arrtable){
  					$str_order = '';
  					foreach($arrtable as $key_name => $order_type){
    					if(in_array($key_table, $table) && $key_name != '' && $key_table != '' && $order_type != ''){
    						$str_order .= "{$key_table}__{$key_name} {$order_type}, ";
    					}
  					}
  				}
    			$order_by = $this->remove_string($str_order, 2);
    			if($order_by != '')$order_by = "ORDER BY $order_by";
    		}
      }

      //ROWS
      foreach($table as $no_table => $name_table){
        $name_table_col = $name_table;
        $name_table = $this->get_table_name($name_table, $db);
        $where_table = $val_join = '';
      	$arr_rows = $this->key($name_table, $arr_sql_fields);
        if(is_string($this->key($no_table, $options)))$val_join = mb_strtoupper(trim($this->key($no_table, $options)));
      	if(!in_array($val_join, array('LEFT', 'RIGHT', 'INNER')))$val_join = '';

        //NEW WAY TO PASS ROWS AS SELECT ARGS
        $row_select = $this->key($name_table, $select);
        if($this->is_content($row_select)){
          foreach($row_select as $row_name => $row_rename){
            $rows .= "{$row_name} AS {$name_table}__{$row_rename}, ";
          }
        }

        if($this->is_content($arr_rows)){
          foreach($arr_rows as $nrtab => $field_name){

    	      //ROWS
            if(!$this->is_content($select)){
    	        $rows .= "{$name_table}.{$field_name} AS {$name_table}__{$field_name}, ";
            }

            //WHERE UPDATE VERSION
            $arr_table_where = $this->get_table_where($array_criteria_where, $where_criteria, $name_table, $field_name);
            $where_table .= $this->key('where', $arr_table_where);
            $params = $this->key('params', $arr_table_where);
            if($this->is_content($params)){foreach($params as $kp => $vparam)$all_params[] = $vparam;}
            //WHERE UPDATE VERSION
          }
          if($where_table != '')$where_table = "WHERE 1 {$where_table}";
          $sql = ("SELECT * FROM {$db}{$name_table_col} {$where_table}");
          $sql_join .= "($sql) $name_table $val_join JOIN ";
        }
      }

      //DATA
      $sql_join = $this->remove_string($sql_join, 6);
      $rows = $this->remove_string($rows, 2);

      if(count($table) > 1)$sql_join = "SELECT $rows FROM ({$sql_join} {$keys}) $group_by $order_by $limit";
      else $sql_join = "SELECT $rows FROM {$sql_join} {$keys} $group_by $order_by $limit";

      $sql_join = str_replace(['NULLAND', ')AND'], ['NULL AND', ') AND'], $sql_join);
      $return = $this->sql($sql_join, $all_params);
  		$return['desc'] = $arr_sql_desc;
  		return $return;
    }
  }

  public function description($table = [], $db = ''){
    $arr_not_null = $arr_rows_table = $arr_rows = $arr_sql_desc_key = $arr_key_pairs = $arr_sql_field_key = $arr_key_pairs_table = [];
    if($this->is_content($table)){
      foreach($table as $no_table => $name_table){
        $name_table_col = $name_table;

        $sql = "SHOW FULL COLUMNS FROM {$db}{$name_table_col}";
        $arr_sql_desc = $this->sql($sql);
        if($this->is_content($arr_sql_desc)){
          $name_table = $this->get_table_name($name_table, $db);
          $fetch = $this->key('fetch', $arr_sql_desc);
          foreach($fetch as $kdesc => $vdesc){
            $key_desc = $this->key('Key', $vdesc);
            $field_desc = $this->key('Field', $vdesc);
            $field_null = $this->key('Null', $vdesc);
            $field_comment = $this->key('Comment', $vdesc);

            if($field_null == 'NO')$arr_not_null[$name_table][$field_desc] = $field_comment;

            $arr_sql_field_key[$name_table][$field_desc] = $field_desc;
            if($key_desc == 'PRI')$arr_sql_desc_key[$field_desc] = $field_desc;

            $arr_rows["$name_table.$field_desc"] = $vdesc;
            $arr_rows_table[$name_table][$field_desc] = $field_desc;
          }

          if($this->is_content($arr_sql_field_key)){
            foreach($arr_sql_field_key as $namettable => $arr_ttable){
              foreach($arr_ttable as $kfield => $nfield){
                if(array_key_exists($nfield, $arr_sql_desc_key)){
                  $arr_key_pairs[$nfield][$namettable] = "{$namettable}.{$nfield}";
                  $arr_key_pairs_table[$namettable][$nfield] = $nfield;
                }
              }
            }
          }
        }
      }
    }
    $return = ['all_rows' => $arr_rows_table, 'key' => $arr_key_pairs, 'not_null' => $arr_not_null, 'rows' => $arr_rows, 'fields' => $arr_sql_field_key];
    return $return;
  }

  private function insert($data){
    $return = [];
    $arr_insert = $this->key('values', $data);
    $on_duplicate = $this->key('duplicate', $data, FALSE);
    if($this->is_content($arr_insert)){

      $table = [];
      foreach($arr_insert as $name_table => $insert){
        $table[] = $name_table;
      }

      $arr_sql_desc = $this->description($table);
      $arr_not_null = $this->key('not_null', $arr_sql_desc);

      foreach($arr_insert as $name_table => $insert){
        $str_empty = $set_table = $set_values = '';
       	$params = [];

        /*-------- SET --------*/
        $to_field = [];
        if($this->is_content($insert)){
          foreach($insert as $field_name => $value){

            //MASIVE INSERT ADD 200820
            if($this->is_content($value)){
              //echo '<pre>', print_r($field_name, TRUE), '</pre>';
              //echo '<pre>', print_r($value, TRUE), '</pre>';
              $n_set_table = '';
              foreach($value as $n_field_name => $n_value){
                if(isset($arr_not_null[$name_table][$n_field_name]) && $n_value == ''){
                  $str_empty .= "<li>{$arr_not_null[$name_table][$n_field_name]}</li>";
                }else{
                  $to_field[$n_field_name] = $n_field_name;
                  //if($n_value != '' || $n_value == NULL){
                    $n_set_table .= '?,';
                    $params[] = $n_value;
                  //}
                }
              }
              $n_set_table = $this->remove_string($n_set_table, 1);
              $set_table .= "({$n_set_table}), ";
            }else{
              if($value == NULL){
                $set_table .= " $field_name = NULL,";
                //$params[] = NULL;
              }else{
                if(isset($arr_not_null[$name_table][$field_name]) && $value == ''){
                  $str_empty .= "<li>{$arr_not_null[$name_table][$field_name]}</li>";
                }
                if($value != ''){
                  $set_table .= "{$field_name} = ?, ";
                  $params[] = $value;
                }
              }
            }
          }
        }
        $set_table = $this->remove_string($set_table, 2);
        /*-------- SET --------*/
        if($this->is_content($to_field)){
          $to_field_str = implode(',', $to_field);
          if($to_field_str != ''){
            if($on_duplicate){
              $table_duplicate = '';
              foreach($to_field as $k => $name_row){
                $table_duplicate .= "{$name_row} = _VALUES.{$name_row},";
              }
              $table_duplicate = $this->remove_string($table_duplicate, 1);
              $sql = "INSERT INTO {$name_table} ({$to_field_str}) VALUES $set_table AS _VALUES ON DUPLICATE KEY UPDATE {$table_duplicate}";
            
            }else $sql = "INSERT INTO {$name_table} ({$to_field_str}) VALUES $set_table";
          }else $params = [];
        }else $sql = "INSERT INTO {$name_table} SET $set_table";
        $return[$name_table]['state'] = FALSE;
        $return[$name_table]['sql'] = $this->query_print($sql, $params);

        if($str_empty != '')$return[$name_table]['message'] = "Fill:<ul>{$str_empty}</ul>";
        else{
          if($this->is_content($params) && $set_table != '')$return[$name_table] = $this->sql($sql, $params, FALSE, TRUE);
          else $return[$name_table]['message'] = 'Check empty params';
        }
      }
    }
    return $return;
  }

  private function delete($data){
    $return = [];
    $db = $this->get_database_name($this->key('db', $data));
    $table = $this->key('table', $data);
    $where_criteria = $this->key('where', $data);
    $array_criteria_where = $this->criteria();
    if($this->is_content($table)){

      foreach($table as $no_table => $name_table){
        $name_table = $this->get_table_name($name_table, $db);
        $where_table = '';
        $params = [];

        //-------- WHERE --------
        if(array_key_exists($name_table, $where_criteria) && !empty($where_criteria[$name_table]) && is_array($where_criteria[$name_table])){

          foreach($where_criteria[$name_table] as $field_name => $array_criteria_data){
            //WHERE UPDATE VERSION
            $arr_table_where = $this->get_table_where($array_criteria_where, $where_criteria, $name_table, $field_name);
            $where_table .= $this->key('where', $arr_table_where);
            $all_params = $this->key('params', $arr_table_where);
            if($this->is_content($all_params)){foreach($all_params as $kp => $vparam)$params[] = $vparam;}
            //WHERE UPDATE VERSION
          }
        }
      	$where_table = trim(substr(trim($where_table), 3));
      	if($where_table != '')$where_table = "WHERE {$where_table}";
        $sql = "DELETE FROM {$db}{$name_table} $where_table";
        $return['sql'][$name_table] = $this->query_print($sql, $params);

        if($this->is_content($params) && $where_table != ''){
          $arr_data_rows = $this->sql($sql, $params, FALSE);
          $return[$name_table] = $arr_data_rows;
        }else{
          $return['state'] = TRUE;
          $return['message'] = 'Not allowed empty where';
        }
      }
    }
    return $return;
  }

  private function get_table_name($name_table, $db){
    if($db == '')$name_table = substr(strstr($name_table, '.', FALSE), 1);
    return $name_table;
  }

  private function get_database_name($db){
    if($db != '' && strpos($db, '.') === false)$db = "{$db}.";
    return $db;
  }

  private function query_print($sql, $params){
    $arr_join = explode('?', $sql);
    $str_sql = '';
    foreach ($arr_join as $kj => $vj) {
      $str_sql .= $vj;
      if(array_key_exists($kj, $params))$str_sql .= "'{$params[$kj]}'";
    }
    return $str_sql;
  }

  private function get_table_where($array_criteria_where, $where_criteria, $name_table, $field_name){
    $params = [];
    $where_table = '';

    if(isset($where_criteria[$name_table][$field_name]) && is_array($where_criteria[$name_table][$field_name])){
      $array_criteria_data = $where_criteria[$name_table][$field_name];
      $value_criteria = $this->key('value', $array_criteria_data);
      $option_sel_criteria = $this->key('option', $array_criteria_data);
      if(is_numeric($option_sel_criteria)){

        if(!in_array($option_sel_criteria, [9, 10])){
          if($option_sel_criteria == 3){
            $option_criteria = $this->key(2, $array_criteria_where);
            if(is_string($value_criteria))$value_criteria = "%{$value_criteria}%";

          }elseif(in_array($option_sel_criteria, [7, 8])){
            if($option_sel_criteria == 7)$option_criteria = 'IN';
            else $option_criteria = 'NOT IN';

          }else $option_criteria = $this->key($option_sel_criteria, $array_criteria_where);
        }

        //NOT AND IS NULL OPTION
        if(in_array($option_sel_criteria, [9, 10])){
          if($option_sel_criteria == 9)$where_table = " AND {$field_name} IS NULL";
          else $where_table = " AND {$field_name} IS NOT NULL ";

        //BETWEEN
        }elseif(in_array($option_sel_criteria, [11, 12])){
          $array_pdo = $this->where($value_criteria);
          foreach($array_pdo['array'] as $kpdo => $vpdo)$params[] = $vpdo;

          if($option_sel_criteria == 11)$where_table = " AND {$field_name} BETWEEN ? AND ? ";
          else $where_table = " AND {$field_name} NOT BETWEEN ? AND ? ";

        }elseif(is_array($value_criteria)){
          $array_pdo = $this->where($value_criteria);
          $where_table .= " AND $field_name {$option_criteria} ({$array_pdo['where']}) ";
          foreach($array_pdo['array'] as $kpdo => $vpdo)$params[] = $vpdo;

        }else{
          if($option_criteria != ''){
            $where_table .= " AND {$field_name} {$option_criteria} (?) ";
            $params[] = $value_criteria;
          }
        }
      }
    }
    return ['where' => $where_table, 'params' => $params];
  }
}
