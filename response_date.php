<?php
/**
 * Code to get last response date for a service desk.
 * 
 * @author Hilmar Kári Hallbjörnsson (drupalviking@gmail.com)
 * @author Sölvi Páll Ásgeirsson (Easter day calculations)
 * @license MIT
 */
define ('DAYS_TO_RESPOND', 2);
define ('OPEN_AT', '8:20');
define ('OPENING_HOUR', 8);
define ('OPENING_MINUTE', 20);
define ('OPEN_FOR_HOURS', 8);

/**
 * Returns the last day a ticket can be responded to.
 *
 * This algorithm takes into consideration, weekends, opening hours and holidays to determine when the last day is.
 *
 * @param DateTime $day
 * @return DateTime
 */
function get_last_response_date(DateTime $day) {
  $last_response_date = $day;
  $days_to_respond = DAYS_TO_RESPOND;

  //Check to see if the service desk is open (hour wise). If it isn't, then add one day
  //to the response time.
  $opening_hour = new DateTime($last_response_date->format('Y-M-d' . ' ' . OPEN_AT));
  $dateVal = $last_response_date->diff($opening_hour);
  if($dateVal->h >= OPEN_FOR_HOURS) {
    $last_response_date->modify('+1 day');
    $last_response_date->setTime(OPENING_HOUR,OPENING_MINUTE);
  }
  elseif($dateVal->h < 0 || $dateVal->invert == 0) {
    $last_response_date->setTime(OPENING_HOUR,OPENING_MINUTE);
  }

  //Check to see if the day is a holiday. If it is, add one day to the response time.
  if(is_day_a_holiday($last_response_date)) {
    $last_response_date->modify('+1 day');
  }

  //While we still have some response time left, check to see if the day is a holiday or not.
  //We always add one day to the response, but if it wasn't a holiday, we deduct one day from the
  //total days to respond.
  while($days_to_respond > 0) {
    if(is_day_a_holiday($last_response_date)) {
      $last_response_date->modify('+1 day');
    }

    else {
      $last_response_date->modify("+1 day");
      $days_to_respond--;
    }
  }

  //After we have deducted all the response days, we'll still have to check if that day is a holiday or not.
  //Therefor we have to go over the holidays once again.
  while(is_day_a_holiday($last_response_date)) {
    $last_response_date->modify('+1 day');
  }

  return $last_response_date;
}

/**
 * Determines if a day is a holiday (public or official) or not.
 *
 * @param DateTime $day
 * @return bool
 */
function is_day_a_holiday(DateTime $day) {
  $holidays = get_holidays_for_year($day->format('Y'), $day->format('H:i'));
  foreach($holidays as $holiday) {
    if( $holiday == $day ) {
      return true;
    }
  }
  if(is_day_on_a_weekend($day)) {
    return true;
  }

  return false;
}

/**
 * Determines if a day is on a weekend or not
 * 
 * @param DateTime $day
 * @return bool
 */
function is_day_on_a_weekend(DateTime $day) {
  $weekday = $day->format('w');
  if($weekday == 6 || $weekday == 0 )
    return true;
  return false;
}

/**
 * Returns an array of all holidays for a given year
 *
 * @param $year
 * @return array
 */
function get_holidays_for_year($year, $time) {
  $holidays = [];
  $holidays['new_years_day'] = new DateTime($year . '-01-01 ' . $time);
  $holidays['may_first'] = new DateTime($year . '-05-01 ' . $time);
  $holidays['june_seventeenth'] = new DateTime($year . '-06-17 ' . $time);
  $holidays['christmas_eve'] = new DateTime($year . '-12-24 ' . $time);
  $holidays['christmas_day'] = new DateTime($year . '-12-25 ' . $time);
  $holidays['second_of_xmas'] = new DateTime($year . '-12-26 ' . $time);
  $holidays['new_years_eve'] = new DateTime($year . '-12-31 ' . $time);
  $holidays['maundy_thursday'] = get_easter_sunday($year, $time)->modify('-3 day');
  $holidays['great_friday'] = get_easter_sunday($year, $time)->modify('-2 day');
  $holidays['easter_sunday'] = get_easter_sunday($year, $time);
  $holidays['easter_monday'] = get_easter_sunday($year, $time)->modify('+1 day');
  $holidays['ascension_of_jesus'] = get_easter_sunday($year, $time)->modify('+40 day');
  $holidays['petecost'] = get_easter_sunday($year, $time)->modify('+49 day');
  $holidays['with_monday'] = get_easter_sunday($year, $time)->modify('+50 day');
  $holidays['first_day_of_summer'] = get_first_day_of_summer($year, $time);
  $holidays['merchant_holiday'] = get_merchant_holiday($year, $time);

  return $holidays;
}

/**
 * Returns the date when Easter sunday comes up for given year.
 *
 * Code was aquired here (Python code) and adapted to PHP : https://github.com/solvip/icelandic_holidays/blob/master/icelandic_holidays.py
 *
 * @author Sölvi Páll Ásgeirsson (Python code)
 * @license MIT
 * @param $year
 * @return DateTime
 */
function get_easter_sunday($year, $time) {
  $a = $year % 19;
  $b = (int)($year / 100);
  $c = (int)($year % 100);
  $d = (int)($b / 4);
  $e = $b % 4;
  $f = (int)(($b + 8) / 25);
  $g = (int)(($b - $f + 1) / 3);
  $h = (19 * $a + $b -$d -$g + 15) % 30;
  $i = (int)($c / 4);
  $k = $c % 4;
  $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
  $m = (int)(($a + 11 * $h + 22 * $l) / 451);
  $month = (int)(($h + $l - 7 * $m +114) / 31);
  $day = ((($h + $l - 7 * $m + 114) % 31) + 1);

  return new DateTime($year . '-' . $month . '-' . $day . ' ' . $time);
}

/**
 * Returns the date for first day of summer in Iceland for any given year
 *
 * The first day of summer is always the third Thursday in April
 *
 * @param $year
 * @return DateTime|static
 */
function get_first_day_of_summer($year, $time) {
  $day = new DateTime($year . '-04-19 ' . $time);
  while(true) {
    if($day->format('w') == 4) {
      break;
    }
    $day = $day->modify('+1 day');
  }

  return $day;
}

/**
 * Returns the date for the Merchant holiday in Iceland for any given year
 *
 * The Merchant holiday is always the first Monday in August
 *
 * @param $year
 * @return DateTime|static
 */
function get_merchant_holiday($year, $time) {
  $day = new DateTime($year . '-08-01 ' . $time);
  while(true) {
    if($day->format('w') == 1) {
      break;
    }
    $day = $day->modify('+1 day');
  }

  return $day;
}