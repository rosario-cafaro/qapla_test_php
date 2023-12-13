<?php
/**
 * No Guzzle? No problem
 *
 */

const PUBLIC_URL = "https://track.amazon.it/tracking/";
const API_URL = "https://track.amazon.it/api/tracker/";
const LOCALIZATION_URL = "https://track.amazon.it/getLocalizedStrings";

/**
 * Localization map (Static fallback if the POST fails)
 */
const LOCALIZATION_MAP = [
    "swa_rex_delivering_no_updated_eddday" => "Consegnato",
    "swa_rex_detail_pickedUp" => "Pacco ritirato",
    "swa_rex_arrived_at_sort_center" => "Il pacco è arrivato presso la sede del corriere",
    "swa_rex_ofd" => "In consegna",
    "swa_rex_detail_creation_confirmed" => "Etichetta creata",
    "swa_rex_shipping_label_created" => "Etichetta creata",
    "swa_rex_detail_departed" => "Il pacco ha lasciato la sede del corriere"
];

/**
 * Get the parameters
 */
//$tracking_number = filter_input(INPUT_GET, "tracking", FILTER_SANITIZE_STRING); // deprecated in 8.1
$tracking_number = htmlentities(filter_input(INPUT_GET, "tracking") ?? "");
$json_flag = intval(htmlentities(filter_input(INPUT_GET, "json", FILTER_SANITIZE_NUMBER_INT) ?? 0));

/**
 * Check mandatory parameter/s
 */
if (strlen($tracking_number) <= 0) {
    if ($json_flag === 1) {
        header('Content-type: application/json');
        echo json_encode([
            "response" => 422,
            "msg" => "Missing Tracking number!"
        ]);
    } else {
        echo "Missing Tracking number!";
    }
    exit();
}

/**
 * Get the page/JSON content
 */
$amazon_tracker_api_response = file_get_contents(API_URL . "$tracking_number");
$api_response = json_decode($amazon_tracker_api_response, true);

/**
 * Init the localization keys array
 */
$localizationKeys = [];

/**
 * Decode inner elements
 */
$progressTracker = json_decode($api_response["progressTracker"], true);
$eventHistory = json_decode($api_response["eventHistory"], true)["eventHistory"];

/**
 * Add the keys that needs to be translated
 */
array_walk_recursive($progressTracker, function ($v, $k) use (&$localizationKeys) {
    if ($k == 'localisedStringId')
        $localizationKeys[] = $v;
});

array_walk_recursive($eventHistory, function ($v, $k) use (&$localizationKeys) {
    if ($k == 'localisedStringId')
        $localizationKeys[] = $v;
});

/**
 * Get the localizations' values
 */
$data = ["localizationKeys" => $localizationKeys];

$headers = [
    'Accept: application/json',
    'Accept-Language: en-US,en;q=0.5',
    'Accept-Encoding: gzip, deflate, br',
    'Content-Type: application/json',
    'anti-csrftoken-a2z: hMOny/PlioLqIfCNSnGcF2OEGuNO1XVbQmal8o6eICdsAAAAAGV4w9kAAAAB',
    'Connection: keep-alive',
    'Referer: https://track.amazon.it/tracking/' . $tracking_number,
    'Cookie: session-id=257-5784047-3884732; session-id-time=2082787201l; csm-hit=tb:s-VYH1DKCJS293CC44TVHZ|1702413273725&t:1702413274591&adb:adblk_no; ubid-acbit=258-7911589-2663967; session-token=9/TU7XVBwIubpQKjYyLs4bsqMYKoO1cs30OnB8f4aZAnRxEd9nSI+E7DmR+62uZnUNtPP/pghsTqeIWUuCKCqpGpAJfDnycClA6/DFDMvf62rsur5ayeC0YbvhHLRXq+ac1wkulN1oitnWp8xEGJuwOhnH78MNhvNBqiaFzm1ukmuBnZhE6ft40A5DbXR86M3h3wvIEF/qHdjJCg6mN+kSEocqhiCKwicTE508pkO90wRCQUp4AmCuDVNE1yE9r5pZFb1LvM1I9cFMv5LR5fD/UZ3MLCtrxkLxUYiRb+cTCKSeZlvB5UVuenTGfgi49gwEixWxU/16DhqLQXRWlQtuc+Y1yNc8dZ',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, LOCALIZATION_URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$curl_response = json_decode(curl_exec($ch), true);

/**
 * Ignore eventual errors, use the fallback static array
 */
//if (curl_errno($ch)) {
//    // Handle eventual error/s
//    echo 'Curl error: ' . curl_error($ch);
//}

$localization_map = (count($curl_response) >= 1) ? $curl_response : LOCALIZATION_MAP;

curl_close($ch);


/**
 * Parse the JSON and build the response array
 */
$response = [
    "shipper" => [
        "label" => "Ordine effettuato presso",
        "value" => $api_response["shipperDetails"]["shipperName"]
    ]
];

$response["expectedDeliveryDate"] = [
    "label" => "Data di consegna prevista",
    "value" => $progressTracker["expectedDeliveryDate"]
];

/*
Custom Logic in trackingStatus_logics_chunk.js to generate the key for the getLocalizedStrings call

e.g.
"swa_rex_summary_delayed_same_edd" => "In transito, ma in ritardo",

DELAYED_AND_NO_EDD_UPDATED: {
    value: "DELAYED_AND_NO_EDD_UPDATED",
    showTracker: !1,
    showOtpEligibility: !0,
    summaryString: "swa_rex_summary_delayed_same_edd",
    summaryDescString: "swa_rex_summary_desc_delayed_no_edd_updated",
    RETURN_TRACKING_PROFILE: {
        value: "RETURN_DELAYED_AND_NO_EDD_UPDATED",
        showTracker: !0,
        summaryString: "swa_rex_summary_delayed_no_edd_return",
        summaryDescString: "swa_rex_summary_desc_delayed_no_edd_updated_return"
    }
},
*/
$response["status"] = [
    "label" => "Stato",
    "value" => $progressTracker["summary"]["metadata"]["trackingStatus"]["stringValue"] . " (" . $progressTracker["summary"]["status"] . ")" // see trackingStatus_logics_chunk.js
];

$response["history"] = [
    "label" => "Storico",
    "value" => []
];
foreach ($eventHistory as $event) {
    $tmp = [
        "Stato spedizione" => ($localization_map[$event["statusSummary"]["localisedStringId"]] ?? $event["eventCode"]),
        "Data" => $event["eventTime"]
    ];
    if (count($event["location"]) >= 1) {
        $address = $event["location"]["city"] . ", " . $event["location"]["stateProvince"] . ", " . $event["location"]["countryCode"] . ", " . $event["location"]["postalCode"];
        $tmp["Luogo"] = $address;
    }
    $response["history"]["value"][] = $tmp;
}

/**
 * Output the response based on the input parameters
 */
if ($json_flag === 1) {
    header('Content-type: application/json');
    echo json_encode([
        "response" => 200,
        "data" => $response
    ]);
} else {
    foreach ($response as $row) {
        echo "<strong>" . $row["label"] . ": </strong> ";
        if (is_array($row["value"])) {
            echo "<br/>";
            foreach ($row["value"] as $el) {
                foreach ($el as $key => $value) {
                    echo "&emsp;<strong>" . $key . ": </strong> ";
                    echo $value . "<br/>";
                }
                echo "<br/>";
            }
        } else {
            echo $row["value"] . "<br/>";
        }
    }
}