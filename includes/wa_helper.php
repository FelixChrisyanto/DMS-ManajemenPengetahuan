<?php
// includes/wa_helper.php

function sendWA($target, $message, $url = null) {
    $token = "PzDWYYaVmR66sdqCFn8A";
    
    // Auto-format local number to international
    $target = preg_replace('/[^0-9]/', '', $target);
    if (strpos($target, '0') === 0) {
        $target = '62' . substr($target, 1);
    }

    $postData = array(
        'target' => $target,
        'message' => $message,
        'countryCode' => '62',
    );
    
    if ($url && strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
        $postData['url'] = $url;
    }
    
    // Jika localhost, tambahkan link foto/PDF di teks saja agar tidak error di Fonnte
    if ($url && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        $label = ($ext == 'pdf') ? "Download Resi PDF" : "Foto Barang";
        $postData['message'] .= "\n\n" . $label . ": " . $url;
    }

    $curl = curl_init();
    // ... (rest of the curl code)
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => array(
            "Authorization: $token"
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    return $response;
}
?>
