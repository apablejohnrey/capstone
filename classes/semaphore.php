<?php
class Semaphore {
    private $apiKey = 'your_api_key_here';

    public function send(array $recipients, string $message): bool {
        $numbers = implode(',', $recipients);
        $data = [
            'apikey' => $this->apiKey,
            'number' => $numbers,
            'message' => $message,
            'sendername' => 'SEMAPHORE'
        ];

        $ch = curl_init('https://api.semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output && strpos($output, 'message_id') !== false;
    }
}
