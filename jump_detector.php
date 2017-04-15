<?php

/*------------Main Program-------------------*/
$columns_separator = "\t";
$rows_separator = PHP_EOL;

$file_consumers_path = 'output_data_consumers_pr_';
$input_file_path = 'combined_data_pr_';

for ($i = 1; $i <= 2; $i++) {
    $file_consumers_path_full = $file_consumers_path . $i . '.txt';
    $input_file_path_full = $input_file_path . $i . '.txt';

    $content = file_get_contents($input_file_path_full);
    $content_array = explode($rows_separator, $content);
    $last_el = $content_array[count($content_array) - 1];
    if (empty($last_el)) {
        array_pop($content_array);
    }

    $title_row = array_shift($content_array);
    write_string_to_file($title_row, $file_consumers_path_full, FALSE);

    $prices = get_hourly_prices_array($content_array);

    $weekly_mean_for_hour = get_hourly_mean_for_week($prices);
    $price_returns = get_return_of_price_array($prices, $i);
    $mean_returns = get_weekly_mean_for_returns($price_returns);//get_hourly_mean_for_week($price_returns);
    $std_deviations = get_hourly_std_deviation_for_week($price_returns);
    $adjusted_returns = get_adjusted_price_returns_array($price_returns, $mean_returns);
    $deltas = get_deltas($adjusted_returns, $std_deviations);
    $jumps = get_jump_dummy_variables($adjusted_returns, $std_deviations);

    $zero_prices_days = get_days_with_zero_prices($content_array, $prices);

    $full_strings = combine_full_strings_array($content_array, $std_deviations,
        $weekly_mean_for_hour, $price_returns, $adjusted_returns, $deltas,  $jumps);
    foreach ($full_strings as $string){
        write_string_to_file($string, $file_consumers_path_full, TRUE);
    }
}
/*------------end of main program-----------------*/

function write_string_to_file($string, $file_path, $append = FALSE) {
    if ($append) {
        file_put_contents($file_path, $string, FILE_APPEND);
    } else {
        file_put_contents($file_path, $string);
    }
}

function get_hourly_prices_array($content_array) {
    global $columns_separator;
    $hourly_prices = array();

    foreach ($content_array as $row) {
        $columns = explode($columns_separator, $row);
        $price = (float)$columns[2];
        $hourly_prices[] = $price;
    }
    return $hourly_prices;
}

/**
 * Calculates std dev from weekly mean returns for hour i
 */
function get_hourly_std_deviation_for_week($prices) {
    $std_dev = $prices;
    $hour_iterator = 24;
    $week_iterator = 24 * 7;

    $max_index = count($prices);

    for ($i = 0; $i < $max_index + $week_iterator; $i += $week_iterator) {
        $period_start = $i;
        $period_end = $period_start + $week_iterator;

        //Check if last week of the year
        if ($period_end > $max_index) {
            $period_start = $max_index - $week_iterator;
            $period_end = $max_index;
        }

        for ($h = 0; $h < 24; $h++) {
            $hour_index_start = $period_start + $h;

            //Step 1: collect data, pass it to necessary math fn, calculate .
            //Step 2: replace values in output array
            $sample = array();
            $sample_std_dev = 0;
            for ($step = 1; $step < 3; $step++) {
                for ($z = $hour_index_start; $z < $period_end; $z += $hour_iterator) {
                    if ($step == 1) {
                        $sample[] = $prices[$z];
                    } else {
                        $std_dev[$z] = $sample_std_dev;
                    }
                }
                if ($step == 1) {
                    $sample_std_dev = standard_deviation($sample, TRUE);
                }
            }
        }
    }

    return $std_dev;
}

function standard_deviation($aValues, $bSample = false)
{
    $fMean = array_sum($aValues) / count($aValues);
    $fVariance = 0.0;
    foreach ($aValues as $i)
    {
        $fVariance += pow($i - $fMean, 2);
    }
    $fVariance /= ( $bSample ? count($aValues) - 1 : count($aValues) );
    return (float) sqrt($fVariance);
}

function get_hourly_mean_for_week($prices) {
    $hourly_means = $prices;
    $hour_iterator = 24;
    $week_iterator = 24 * 7;

    $max_index = count($prices);

    for ($i = 0; $i < $max_index + $week_iterator; $i += $week_iterator) {
        $period_start = $i;
        $period_end = $period_start + $week_iterator;

        //Check if last week of the year
        if ($period_end > $max_index) {
            $period_start = $max_index - $week_iterator;
            $period_end = $max_index;
        }

        for ($h = 0; $h < 24; $h++) {
            $hour_index_start = $period_start + $h;

            //Step 1: collect data, pass it to necessary math fn, calculate .
            //Step 2: replace values in output array
            $sample = array();
            $sample_mean = 0;
            for ($step = 1; $step < 3; $step++) {
                for ($z = $hour_index_start; $z < $period_end; $z += $hour_iterator) {
                    if ($step == 1) {
                        $sample[] = $prices[$z];
                    } else {
                        $hourly_means[$z] = $sample_mean;
                    }
                }
                if ($step == 1) {
                    $sample_mean = array_sum($sample) / count($sample);
                }
            }
        }
    }

    return $hourly_means;
}

/**
 * r(t,i) = ln P(t,i) - ln P(t,i-1)
 */
function get_return_of_price_array($prices, $price_zone_number) {
    $price_returns = array();

    $prices_array_size = count($prices);
    $pre_value = ($price_zone_number == 1) ? 950.54 : 992.87; //Price at 31.12.2014 23:00
    $beginning_border_value = $prices[0] - $pre_value;//log($prices[0]) - log($pre_value);

    $price_returns[] = $beginning_border_value;
    for ($i = 1; $i < $prices_array_size; $i++) {
        $price_returns[] = $prices[$i] - $prices[$i-1];//log($prices[$i]) - log($prices[$i - 1]);
    }

    return $price_returns;
}

function get_weekly_mean_for_returns($price_returns) {
    $hourly_means = $price_returns;
    $week_iterator = 24 * 7;

    $max_index = count($price_returns);

    for ($i = 0; $i < $max_index + $week_iterator; $i += $week_iterator) {
        $period_start = $i;
        $period_end = $period_start + $week_iterator;

        //Check if last week of the year
        if ($period_end > $max_index) {
            $period_start = $max_index - $week_iterator;
            $period_end = $max_index;
        }

        //Step 1: collect data, pass it to necessary math fn, calculate .
        //Step 2: replace values in output array
        $sample = array();
        $sample_mean = 0;
        for ($step = 1; $step < 3; $step++) {
            for ($z = $period_start; $z < $period_end; $z++) {
                if ($step == 1) {
                    $sample[] = $price_returns[$z];
                } else {
                    $hourly_means[$z] = $sample_mean;
                }
            }
            if ($step == 1) {
                $sample_mean = array_sum($sample) / count($sample);
            }
        }
    }

    return $hourly_means;
}

function get_adjusted_price_returns_array($price_returns, $mean_returns) {
    $adjusted_returns = array();

    $max_index = count($price_returns);
    for ($i = 0; $i < $max_index; $i++) {
        $adjusted_returns[$i] = $price_returns[$i] - $mean_returns[$i];
    }
    return $adjusted_returns;
}

function get_deltas($adjusted_returns, $std_deviations) {
    $deltas = array();

    $max_index = count($adjusted_returns);
    for ($i = 0; $i < $max_index; $i++) {
        $deltas[$i] = $adjusted_returns[$i] - 3 * $std_deviations[$i];
    }
    return $deltas;
}

function get_jump_dummy_variables($adjusted_returns, $std_deviations) {
    $jumps = array();

    $exist = array();

    $max_index = count($adjusted_returns);
    for ($i = 0; $i < $max_index; $i++) {
        $jumps[$i] = (abs($adjusted_returns[$i]) > 3 * $std_deviations[$i]) ? 1 : 0;

        if($jumps[$i] == 1){
            $exist[] = $i;
        }
    }


    return $jumps;
}

function get_days_with_zero_prices ($content_array, $prices){
    global $columns_separator;
    $days = array();
    $max_index = count($prices);

    for ($i = 0; $i < $max_index; $i++) {
       if($prices[$i] == 0){
        $days[] = explode($columns_separator, $content_array[$i])[0];
       }
    }
    return $days;
}

function combine_full_strings_array($content_array, $std_deviations,
                                    $weekly_mean_for_hour, $price_returns,
                                    $adjusted_returns, $deltas, $jumps) {
    global $rows_separator;
    global $columns_separator;

    $full_strings = array();
    for ($i = 0; $i < count($content_array); $i++) {
        $full_strings[$i] =
            substr($content_array[$i], 0, -1) . $columns_separator .
            $std_deviations[$i] . $columns_separator .
            $weekly_mean_for_hour[$i] . $columns_separator .
            $price_returns[$i] . $columns_separator .
            $adjusted_returns[$i] . $columns_separator .
            $deltas[$i] . $columns_separator .
            $jumps[$i] . $rows_separator;
    }
    return $full_strings;
}