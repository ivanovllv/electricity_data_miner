<?php
function get_full_dom_from_url($url) {
    $dom = new DOMDocument();
//    libxml_use_internal_errors(true);
    $dom->loadHTMLFile($url);
    return $dom;
}

function get_consumers_table($full_dom, $price_zone) {
    $tbody_id = 'block-lx-js-data-results-rsv-indexes-indexes' . $price_zone;
    $tbody = $full_dom->getElementById($tbody_id);
    $rows = $tbody->getElementsByTagName('tr');
    $table_array_tmp = array();
    $table_output = array();
    foreach ($rows as $row) {
        $row_string = strval($row->nodeValue);
        array_push($table_array_tmp, $row_string);
    }

    $first_index_of_consumers_table = 1;
    $last_index_of_consumers_table = 25;
    for ($i = $first_index_of_consumers_table; $i <= $last_index_of_consumers_table; $i++) {
        array_push($table_output, $table_array_tmp[$i]);
    }

    return $table_output;
}

function get_suppliers_table($full_dom, $price_zone) {
    $tbody_id = 'block-lx-js-data-results-rsv-indexes-indexes' . $price_zone;
    $tbody = $full_dom->getElementById($tbody_id);
    $rows = $tbody->getElementsByTagName('tr');
    $table_array_tmp = array();
    $table_output = array();
    foreach ($rows as $row) {
        $row_string = strval($row->nodeValue);
        array_push($table_array_tmp, $row_string);
    }

    $first_index_of_suppliers_table = 27;
    $last_index_of_suppliers_table = 51;
    for ($i = $first_index_of_suppliers_table; $i <= $last_index_of_suppliers_table; $i++) {
        array_push($table_output, $table_array_tmp[$i]);
    }

    return $table_output;
}

function get_needed_columns_from_table_numbers($needed_columns, $table) {
    $output_array_of_columns = array();
    foreach ($needed_columns as $col) {
        $output_array_of_columns[$col] = array();
    }
    foreach ($table as $row_str) {
        $row_elements = array_filter(explode(" ", $row_str), 'strlen');
        $row_elements = array_values($row_elements);
        foreach ($needed_columns as $col) {
            array_push($output_array_of_columns[$col], $row_elements[$col]);
        }
    }
    return $output_array_of_columns;
}

function get_title_row($number_of_needed_columns) {
    global $columns_separator;
    global $rows_separator;
    $row = 'Date\\Hours';
    $row .= $columns_separator;
    for ($i = 0; $i < $number_of_needed_columns; $i++) {
        for ($j = 0; $j <= 23; $j++) {
            $row .= $j;
            $row .= $columns_separator;
        }
        $row .= 'Total per day';
        if ($i == $number_of_needed_columns - 1) {
            $row .= $rows_separator;//last element, move to new row
        } else {
            $row .= $columns_separator;
        }
    }
    return $row;
}

function combine_single_day_string($day, $numbers_array) {
    global $columns_separator;
    global $rows_separator;
    $row = $day;
    $row .= $columns_separator;
    foreach ($numbers_array as $certain_index) {
        foreach ($certain_index as $value_for_hour) {
            $row .= $value_for_hour;
            $row .= $columns_separator;
        }
    }
    $row .= $rows_separator;
    return $row;
}

function get_combined_final_table_of_numbers($string_of_all_days) {
    global $needed_columns;
    $row = get_title_row(count($needed_columns));
    $row .= $string_of_all_days;
    return $row;
}

function get_list_of_days_of_year($year) {
    $range = array();
    $start = strtotime($year . '-01-01');
    $end = strtotime($year . '-12-31');

    $yesterday = strtotime("yesterday");
    if ($end > $yesterday) {
        $end = $yesterday;
    }

    do {
        $range[] = date('d.m.Y', $start);
        $start = strtotime("+ 1 day", $start);
    } while ($start <= $end);

    return $range;
}

function write_string_to_file($string, $file_path, $append = FALSE) {
//    $file_path = getcwd() . '/output_data_consumers_pr_2.txt';
    if ($append) {
        file_put_contents($file_path, $string, FILE_APPEND);
    } else {
        file_put_contents($file_path, $string);
    }
}

/*---------------Main program--------------*/
$year = 2016;
$needed_columns = array(2);
$current_directory = getcwd();
$columns_separator = "\t";
$rows_separator = PHP_EOL;


$file_consumers_path = 'output_data_consumers_pr_';
$file_suppliers_path = 'output_data_suppliers_pr_';


for ($i = 1; $i <= 2; $i++) {
    $file_consumers_path_full = $file_consumers_path . $i . '.txt';
//    $file_suppliers_path_full = $file_suppliers_path . $i . '.txt';
    write_string_to_file(get_title_row(count($needed_columns)), $file_consumers_path_full, FALSE);
//    write_string_to_file(get_title_row(count($needed_columns)), $file_suppliers_path_full, FALSE);

    foreach (get_list_of_days_of_year($year) as $day) {
        $url = 'https://www.atsenergo.ru/results/rsv/indexes/indexes' . $i . '/index.htm?date=' . $day . '#id42';
        $full_dom = get_full_dom_from_url($url);
        $table_for_consumers = get_consumers_table($full_dom, $i);
//        $table_for_suppliers = get_suppliers_table($full_dom, $i);
        $numbers_for_consumers = get_needed_columns_from_table_numbers($needed_columns, $table_for_consumers);
//        $numbers_for_suppliers = get_needed_columns_from_table_numbers($needed_columns, $table_for_suppliers);
        write_string_to_file(combine_single_day_string($day, $numbers_for_consumers), $file_consumers_path_full, TRUE);
//    write_string_to_file(combine_single_day_string($day, $numbers_for_suppliers), $file_suppliers_path_full, TRUE);

        echo $day . "</br>";
    }
}


//$days_test = array('01.01.2015', '01.02.2015', '01.03.2015');
//for ($i = 1; $i <= 2; $i++) {
//    $file_consumers_path_full = $file_consumers_path . $i . '.txt';
//    $file_suppliers_path_full = $file_suppliers_path . $i . '.txt';
//    write_string_to_file(get_title_row(count($needed_columns)), $file_consumers_path_full, FALSE);
////write_string_to_file(get_title_row(count($needed_columns)), $file_suppliers_path_full, FALSE);
//    foreach ($days_test as $day) {
//        $url = 'https://www.atsenergo.ru/results/rsv/indexes/indexes' . $i . '/index.htm?date=' . $day . '#id42';
//        $full_dom = get_full_dom_from_url($url);
//        $table_for_consumers = get_consumers_table($full_dom, $i);
////        $table_for_suppliers = get_suppliers_table($full_dom, $i);
//        $numbers_for_consumers = get_needed_columns_from_table_numbers($needed_columns, $table_for_consumers);
////        $numbers_for_suppliers = get_needed_columns_from_table_numbers($needed_columns, $table_for_suppliers);
//        write_string_to_file(combine_single_day_string($day, $numbers_for_consumers), $file_consumers_path_full, TRUE);
////        write_string_to_file(combine_single_day_string($day, $numbers_for_suppliers), 'output_data_suppliers_pr_1.txt', TRUE);
//        echo $day . "</br>";
//    }
//}


echo 'dadaza';
