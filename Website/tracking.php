<?php
/**
 * This handles all the tracking on page load *
 */
function tracking()
{
    global $tracking_list;
    $tracking_list[] = [];

    create_tracking("Total", "total");
    create_tracking("Active", "active");
    create_tracking("Locations", "locations");
    create_tracking("Daily", "daily");
    create_tracking("Users", "users");
    create_tracking("Computer", "device_computer");
    create_tracking("Safari", "browser_safari");
    create_tracking("Chrome", "browser_chrome");
    create_tracking("Firefox", "browser_firefox");
    create_tracking("Other Browser", "browser_other");
    create_tracking("Linux", "os_linux");
    create_tracking("Mac", "os_mac");
    create_tracking("Windows", "os_windows");
    create_tracking("iOS", "os_ios");
    create_tracking("Android", "os_android");
    create_tracking("Other OS", "os_other");
    create_tracking("Locations", "locations");
    create_tracking("Unique Users", "unique_users");

    increment_tracking("total");

    global $user_ip;
    $key = slugify("user_ip_".$user_ip);
    create_tracking("IP $user_ip", $key);
    increment_tracking("$key");

    $geo = unserialize(file_get_contents("http://www.geoplugin.net/php.gp?ip=$user_ip"));
    $country = $geo["geoplugin_countryName"];
    $city = $geo["geoplugin_city"];
    global $location;
    $location = slugify($city."_".$country);
    create_tracking("Location $city $country", "location_$location");
    increment_tracking("location_$location");

    // now try it
    global $browser_detail;
    $browser_detail = system_info();

    increment_tracking($browser_detail["browser"]);
    increment_tracking($browser_detail["os"]);
    increment_tracking($browser_detail["device"]);

    do_daily();

}

/**
 * This Creates tracking meta *
 */
function create_tracking($title, $slug)
{
    global $tracking_list;
    $tracking_list[] = [

        "slug" => $slug, "title" => $title

    ];
    if(get_tracking($slug) != null)
    {
        return;
    }
    $id = count(Database::select("*", "tracking"));
    Database::insert("tracking", "ID, title, slug, value", "$id, '$title', '$slug', 0");



}

/**
 * This returns tracking meta *
 */
function get_tracking($slug)
{
    return Database::select("*", "tracking", "slug = '$slug'")[0]["value"];
}

/**
 * This Updates tracking meta *
 */
function update_tracking($slug, $value)
{
    Database:: update("tracking", "value", $value, "slug = '$slug'");
}

/**
 * This increments tracking meta *
 */
function increment_tracking($slug)
{
    $value = get_tracking($slug);
    $value++;
    update_tracking($slug, $value);
}


/**
 * This Updates meta *
 */
function update_meta($key, $value)
{
    if(!has_meta($key)){
        $id = count(Database:: select("*", "meta"));
        Database:: insert("meta", "meta_id, meta_key, meta_value", "$id, '$key', '$value'");
    }else{
        Database:: update("meta", "meta_value", $value, "meta_key = '$key'");
    }
}

/**
 * This returns meta *
 */
function get_meta($key)
{
    return Database:: select("*", "meta", "meta_key = '$key'")[0]["meta_value"];
}


/**
 * This returns if there is meta *
 */
function has_meta($key)
{
    $query = Database:: select("*", "meta", "meta_key = '$key'");
    return is_array($query) && count($query) > 0;
}

/**
 * This returns all ips *
 */
function get_ips()
{
    $ips = Database::select("*", "tracking", "(lower(slug) LIKE 'user_ip_%')");
    update_tracking("unique_users", count($ips));
    return $ips;

}

/**
 * This handles the daily tracking functions *
 */
function do_daily()
{
    $today = date('Ymd');
    create_tracking("Date Tracker $today", "date_$today");
    increment_tracking("date_$today");


    $dates = Database::select("*", "tracking", "(lower(slug) LIKE 'date_%')");
    $average = 0;
    foreach ($dates as $date)
    {
        $average+= $date["value"];
    }
    $average /= count($dates);


    update_tracking("daily", $average);

}

/**
 * This returns all locations *
 */
function get_locations(){
    $locations = Database::select("*", "tracking", "(lower(slug) LIKE 'location_%')");
    update_tracking("locations", count($locations));
    return $locations;
}

/**
 * This returns all active ips *
 */
function get_active(){
    $all = Database::select("*", "meta", "(lower(meta_key) LIKE 'last_active_%')");
    $active = 0;
    $now = date("Ymdhis");

    foreach ($all as $user)
    {
        //adds 10 seconds
        if($user["meta_value"] + 00000000000010 > $now)
        {
            $active++;
        }
    }
    update_tracking("active", $active);

    return $active;
}

/**
 * This returns all stats serialised*
 */
function get_stats_compressed(){
    $list = [];
    global $tracking_list;
    foreach ($tracking_list as $tracker)
    {
        $tracker["count"] = get_tracking($tracker["slug"]);
        $list[] = $tracker;
    }

    return serialize($list);
}


/**
 * This starts a report
 */
function create_report($email, $date_end){

    $user = get_user()["ID"];
    $date_start = date("Ymd");
    $current_stats = get_stats_compressed();
    Database::insert("reports", " date_start, date_end, user, email, stats_start, stats_end, emailed", "$date_start, '$date_end', '$user', '$email', '$current_stats', '', 0");
    $nice_date = date("d/m/Y", strtotime($date_end));
    $body = "<h1>Hey There!</h1>";
    $body .= "<p>Your report has now been started.</p>";
    $body .= "<p>You will receive an report email on $nice_date.</p>";
    $body .= "<p>Thank-you for your report.</p>";
    email($email, "Tracking report started.", $body);

}

/**
 * This is run by a cron check
 */
function check_reports(){
   //loop all reports
    $reports = Database::select("*", "reports");
    $today = date("Ymd");
    foreach ($reports as $report)
    {
        if(!$report["emailed"] || $_GET["email"] == $report["email"]){

            if($today >= date("Ymd", strtotime($report["date_end"]))|| $_GET["email"] == $report["email"])
            {

                $body = "<h1>Hey There!</h1>";
                $body .= "<p>Your report is now ready to view please click the link below to see.</p>";
                $body .= "<p><a href='http://jayden-uni.canberra.host/report?id={$report["ID"]}'>Click here</a></p>";
                $body .= "<p>Thank-you for your report.</p>";
                email($report["email"], "Tracking report completed.", $body);

                $stats_end = get_stats_compressed();
                Database:: update("reports", "emailed", 1, "ID = '{$report["ID"]}'");
                Database:: update("reports", "stats_end", $stats_end, "ID = '{$report["ID"]}'");

                echo "<pre>"; var_dump("Sent email to {$report["email"]}");echo "</pre>";

            }


        }


    }

    echo "<pre>"; var_dump("All reports checked");echo "</pre>";

}

function get_report($id)
{
    return Database::select("*", "reports", "id = '$id'")[0];
}