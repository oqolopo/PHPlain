<?php

class PHPlainMisc {

    static public function baseURI() {
        echo "<script type=\"text/javascript\">baseURI = '$GLOBALS[base_uri]'</script>";
    }

    static public function passwordHash($password) {
		$salt = hash('ripemd160', strtoupper($password));
		return hash('tiger192,4', substr($salt, -20) . $password . substr($salt, 0, 20));
	}

	static public function contentNormalize($content, $delimiter = ' ') {
		return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', $delimiter, $content), $delimiter));
	}

    static public function contentFind($db, $table, $content_col, $hash_col, $content, $delimiter = ' ', $hash = 'CRC32')
    {
        $norm = self::contentNormalize($content, $delimiter);
        foreach ($db->sql("SELECT * FROM $table WHERE $hash_col = $hash('$norm')") as $row)
        {
            if ($norm == self::contentNormalize($row[$content_col], $delimiter)) return $row;
        }
        return NULL;
    }

	static public function processXLSX($file_name, $row_processor) {
		$xlsx = new SimpleXLSX($file_name);
		for ($sheet = 0; $sheet++ < $xlsx->sheetsCount();) {
			$rows = $xlsx->rows($sheet);
			foreach ($rows as $row_index => $row) {
				call_user_func($row_processor, $xlsx->sheetName($sheet), $row_index, $row);
			}
		}
	}

}

?>
