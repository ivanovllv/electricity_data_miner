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
    $last_index_of_consumers_table = 24; //Don't need 'Итого' row
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
    $last_index_of_suppliers_table = 50; //Don't need 'Итого' row
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
    $row =
        'Day t' . $columns_separator .
        'Hour i' . $columns_separator .
        'Price' . $columns_separator .
        'Peak (08:00-22:00)' . $columns_separator .
        'Weekend (Sat, Sun)' . $columns_separator .
        'Winter' . $columns_separator .
        'Spring' . $columns_separator .
        'Summer' . $columns_separator .
        'Standard deviation for hour i, yearly' . $columns_separator .
        'Standard deviation for hour i, weekly' . $columns_separator .
        'Yearly mean' . $columns_separator .
        'Yearly mean for hour i' . $columns_separator .
        'Weekly mean for hour i' . $columns_separator .
        'r(t,i) = ln P(t,i) - ln P(t,i-1)' . $columns_separator .
        'Delta k(t,i)' . $columns_separator .
        'Jump' . $rows_separator;//last element, move to new row

    return $row;
}

function combine_single_day_string($day, $numbers_array) {
    global $columns_separator;
    global $rows_separator;
    $row = '';
    foreach ($numbers_array as $certain_index) {
        foreach ($certain_index as $hour => $value_for_hour) {
            $peak = get_peak_dummy_variable($hour);
            $seasonality = get_string_with_dummy_variables_for_seasonality($day);
            $row .=
                $day . $columns_separator .
                $hour . $columns_separator .
                $value_for_hour . $columns_separator .
                $peak . $columns_separator .
                $seasonality;
            $row .= $rows_separator;
            ;
        }
    }

    return $row;
}

function get_peak_dummy_variable($hour){
    $peak = 0;
    if ($hour >= 8 && $hour <= 22){
        $peak = 1;
    }
    return $peak;
}

//Order of variables: Weekend, Winter, Spring, Summer
function get_string_with_dummy_variables_for_seasonality($day){
    global $columns_separator;
    $weekend = 0;
    if((date('N', strtotime($day)) >= 6)){
        $weekend = 1;
    }
    $winter = 0;
    $spring = 0;
    $summer = 0;
    $month = date('n', strtotime($day));
    switch ($month){
        case 12:
        case 1:
        case 2:
            $winter = 1;
            break;
        case 3:
        case 4:
        case 5:
            $spring = 1;
            break;
        case 6:
        case 7:
        case 8:
            $summer = 1;
            break;

    }
    $string =
        $weekend . $columns_separator .
        $winter . $columns_separator .
        $spring . $columns_separator .
        $summer;
    return $string;
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
    if ($append) {
        file_put_contents($file_path, $string, FILE_APPEND);
    } else {
        file_put_contents($file_path, $string);
    }
}

/*---------------Main program--------------*/
$year = 2015;
$needed_columns = array(2);
$current_directory = getcwd();
$columns_separator = "\t";
$rows_separator = PHP_EOL;


$file_consumers_path = 'output_data_consumers_pr_';
$file_suppliers_path = 'output_data_suppliers_pr_';


for ($i = 1; $i <= 2; $i++) {
    $file_consumers_path_full = $file_consumers_path . $i . '.txt';
    write_string_to_file(get_title_row(count($needed_columns)), $file_consumers_path_full, FALSE);

    foreach (get_list_of_days_of_year($year) as $day) {
        $url = 'https://www.atsenergo.ru/results/rsv/indexes/indexes' . $i . '/index.htm?date=' . $day . '#id42';
        $full_dom = get_full_dom_from_url($url);
        $table_for_consumers = get_consumers_table($full_dom, $i);
        $numbers_for_consumers = get_needed_columns_from_table_numbers($needed_columns, $table_for_consumers);
        write_string_to_file(combine_single_day_string($day, $numbers_for_consumers), $file_consumers_path_full, TRUE);
    }
}



/*---------------Test program--------------*/
//$days_test = array('03.01.2015', '04.01.2015', '13.03.2015', '01.06.2015');
//for ($i = 1; $i <= 2; $i++) {
//    $file_consumers_path_full = $file_consumers_path . $i . '.txt';
//    write_string_to_file(get_title_row(count($needed_columns)), $file_consumers_path_full, FALSE);
//
//    foreach ($days_test as $day) {
//        $url = 'https://www.atsenergo.ru/results/rsv/indexes/indexes' . $i . '/index.htm?date=' . $day . '#id42';
//        $full_dom = get_full_dom_from_url($url);
//        $table_for_consumers = get_consumers_table($full_dom, $i);
//        $numbers_for_consumers = get_needed_columns_from_table_numbers($needed_columns, $table_for_consumers);
//        write_string_to_file(combine_single_day_string($day, $numbers_for_consumers), $file_consumers_path_full, TRUE);
//    }
//}


echo 'Data was fetched successfully!';
