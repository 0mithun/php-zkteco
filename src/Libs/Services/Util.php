<?php

namespace Mithun\PhpZkteco\Libs\Services;

use Mithun\PhpZkteco\Libs\ZKTeco;

class Util
{
    const USHRT_MAX = 65535;

    const CMD_CONNECT = 1000; // Connections requests
    const CMD_EXIT = 1001; // Disconnection requests
    const CMD_ENABLE_DEVICE = 1002; // Ensure the machine to be at the normal work condition
    const CMD_DISABLE_DEVICE = 1003; // Make the machine to be at the shut-down condition, generally demonstrates ‘in the work ...’on LCD

    const CMD_RESTART = 1004; // Restart the machine
    const CMD_POWEROFF = 1005; // Turn Off the machine
    const CMD_SLEEP = 1006; // Sleep the machine
    const CMD_RESUME = 1007; // Resume the machine from Sleep
    const CMD_TEST_TEMP = 1011;
    const CMD_TESTVOICE = 1017; // Voice test to the device
    const CMD_CHANGE_SPEED = 1101;

    const CMD_WRITE_LCD = 66; // Write in LCD
    const CMD_CLEAR_LCD = 67; // Clear LCD

    const CMD_ACK_OK = 2000; // Return value for order perform successfully
    const CMD_ACK_ERROR = 2001; // Return value for order perform failed
    const CMD_ACK_DATA = 2002; // Return data
    const CMD_ACK_UNAUTH = 2005; // Connection unauthorized
    const CMD_ACK_AUTH = 1102; // Connection authorized

    const CMD_PREPARE_DATA = 1500; // Prepares to transmit the data
    const CMD_DATA = 1501; // Transmit a data packet
    const CMD_FREE_DATA = 1502; // Clear machines open buffer

    const CMD_USER_TEMP_RRQ = 9; // Read some fingerprint template or some kind of data entirely
    const CMD_OPTIONS_WRQ = 12; // Write configuration options or custom data to the device.
    const CMD_ATT_LOG_RRQ = 13; // Read all attendance record
    const CMD_CLEAR_DATA = 14; // Clear Data
    const CMD_CLEAR_ATT_LOG = 15; // Clear attendance records
    const CMD_GET_FREE_SIZES = 50; // Clear attendance records

    const CMD_GET_TIME = 201; // Obtain the machine time
    const CMD_SET_TIME = 202; // Set machines time

    const CMD_REG_EVENT = 500; // Register for real-time events

    const CMD_VERSION = 1100; // Obtain the firmware edition
    const CMD_DEVICE = 11; // Read in the machine some configuration parameter

    const CMD_SET_USER = 8; // Upload the user information (from PC to terminal).
    const CMD_USER_TEMP_WRQ = 10; // Upload some fingerprint template
    const CMD_DELETE_USER = 18; // Delete some user
    const CMD_DELETE_USER_TEMP = 19; // Delete some fingerprint template
    const CMD_CLEAR_ADMIN = 20; // Cancel the manager

    const LEVEL_USER = 0; // User level as User
    const LEVEL_ADMIN = 14; // User level as Admin

    const FCT_ATTLOG = 1;
    const FCT_WORKCODE = 8;
    const FCT_FINGERTMP = 2;
    const FCT_OPLOG = 4;
    const FCT_USER = 5;
    const FCT_SMS = 6;
    const FCT_UDATA = 7;

    // Event flags for real-time events (CMD_REG_EVENT)
    const EF_ATTLOG = 1;       // Attendance log event
    const EF_FINGER = 2;       // Fingerprint event
    const EF_ENROLLUSER = 4;   // Enroll user event
    const EF_ENROLLFINGER = 8; // Enroll fingerprint event
    const EF_BUTTON = 16;      // Button pressed event
    const EF_UNLOCK = 32;      // Door unlock event
    const EF_VERIFY = 128;     // Verify event
    const EF_FPFTR = 256;      // Fingerprint feature event
    const EF_ALARM = 512;      // Alarm event

    const COMMAND_TYPE_GENERAL = 'general';
    const COMMAND_TYPE_DATA = 'data';

    const ATT_STATE_FINGERPRINT = 1;
    const ATT_STATE_PASSWORD = 0;
    const ATT_STATE_CARD = 2;

    const ATT_TYPE_CHECK_IN = 0;
    const ATT_TYPE_CHECK_OUT = 1;
    const ATT_TYPE_BREAK_IN = 2;
    const ATT_TYPE_BREAK_OUT = 3;
    const ATT_TYPE_OVERTIME_IN = 4;
    const ATT_TYPE_OVERTIME_OUT = 5;

    // TCP packet header prefix
    const TCP_HEADER = "\x50\x50\x82\x7d";
    const TCP_HEADER_SIZE = 8;

    /**
     * Wrap a packet with TCP header for TCP protocol.
     * TCP packets are prefixed with 0x50 0x50 0x82 0x7D + 4-byte little-endian length.
     *
     * @param string $packet The packet data to wrap.
     * @return string The wrapped packet with TCP header.
     */
    public static function createTcpPacket(string $packet): string
    {
        $length = strlen($packet);
        // TCP header: 4-byte magic + 4-byte length (little-endian)
        return self::TCP_HEADER . pack('V', $length) . $packet;
    }

    /**
     * Remove TCP header from received data.
     * Returns the payload after stripping the 8-byte TCP header.
     *
     * @param string $data The received TCP data.
     * @return string The payload without TCP header.
     */
    public static function stripTcpHeader(string $data): string
    {
        if (strlen($data) > self::TCP_HEADER_SIZE) {
            return substr($data, self::TCP_HEADER_SIZE);
        }
        return $data;
    }

    /**
     * Send data to the ZKTeco device, handling protocol differences.
     *
     * @param ZKTeco $self The ZKTeco instance.
     * @param string $buf The data to send.
     * @return int|false The number of bytes sent, or false on error.
     */
    public static function sendData(ZKTeco $self, string $buf)
    {
        if (property_exists($self, '_protocol') && $self->_protocol === 'tcp') {
            $tcpPacket = self::createTcpPacket($buf);
            self::debugLog($self, 'TCP Send (raw): ' . bin2hex($tcpPacket) . ' (' . strlen($tcpPacket) . ' bytes)');
            return socket_send($self->_zkclient, $tcpPacket, strlen($tcpPacket), 0);
        } else {
            return socket_sendto($self->_zkclient, $buf, strlen($buf), 0, $self->_ip, $self->_port);
        }
    }

    /**
     * Receive data from the ZKTeco device, handling protocol differences.
     * For TCP, extracts one ZKTeco packet and buffers any remaining data.
     *
     * @param ZKTeco $self The ZKTeco instance.
     * @param int $length Maximum length to receive.
     * @return string|false The received data (TCP header stripped for TCP), or false on error.
     */
    public static function recvData(ZKTeco $self, int $length = 1024)
    {
        $data = '';
        if (property_exists($self, '_protocol') && $self->_protocol === 'tcp') {
            // Check if we have buffered data from previous recv
            $buffer = property_exists($self, '_tcp_buffer') ? $self->_tcp_buffer : '';

            if (strlen($buffer) >= self::TCP_HEADER_SIZE) {
                // Try to extract a complete packet from buffer first
                $extracted = self::extractTcpPacket($buffer);
                if ($extracted !== null) {
                    list($packet, $remaining) = $extracted;
                    $self->_tcp_buffer = $remaining;
                    self::debugLog($self, 'TCP Recv (from buffer): ' . bin2hex($packet) . ' (' . strlen($packet) . ' bytes)');
                    return $packet;
                }
            }

            // Need to read more data from socket
            $ret = @socket_recv($self->_zkclient, $data, $length + self::TCP_HEADER_SIZE * 2, 0);
            $data = $data ?? '';
            self::debugLog($self, 'TCP Recv (raw): ' . bin2hex($data) . ' (' . strlen($data) . ' bytes, ret=' . $ret . ')');

            if ($ret !== false && !empty($data)) {
                // Combine with any existing buffer
                $buffer = $buffer . $data;

                // Extract first complete packet and buffer the rest
                $extracted = self::extractTcpPacket($buffer);
                if ($extracted !== null) {
                    list($packet, $remaining) = $extracted;
                    $self->_tcp_buffer = $remaining;
                    self::debugLog($self, 'TCP Recv (stripped): ' . bin2hex($packet) . ' (' . strlen($packet) . ' bytes, buffered=' . strlen($remaining) . ')');
                    return $packet;
                }

                // No complete packet found, buffer all data
                $self->_tcp_buffer = $buffer;
                return '';
            }
            return '';
        } else {
            @socket_recvfrom($self->_zkclient, $data, $length, 0, $self->_ip, $self->_port);
            $data = $data ?? '';
        }
        return $data;
    }

    /**
     * Extract a single TCP packet from raw data.
     * Returns [payload, remaining_data] or null if no complete packet found.
     *
     * @param string $data The raw TCP data with potential ZKTeco header.
     * @return array|null [payload, remaining] or null if no complete packet.
     */
    public static function extractTcpPacket(string $data): ?array
    {
        if (strlen($data) < self::TCP_HEADER_SIZE) {
            return null;
        }

        if (substr($data, 0, 4) !== self::TCP_HEADER) {
            // No valid header found, return data as-is (shouldn't happen)
            return [$data, ''];
        }

        // Extract packet length from header (bytes 4-7, little-endian 32-bit)
        $lenBytes = substr($data, 4, 4);
        $packetLen = unpack('V', $lenBytes)[1];

        $totalPacketLen = self::TCP_HEADER_SIZE + $packetLen;

        if (strlen($data) < $totalPacketLen) {
            // Incomplete packet, need more data
            return null;
        }

        // Extract payload and remaining data
        $payload = substr($data, self::TCP_HEADER_SIZE, $packetLen);
        $remaining = substr($data, $totalPacketLen);

        return [$payload, $remaining];
    }

    /**
     * Strip only the first TCP header and return just that packet's payload.
     * DEPRECATED: Use extractTcpPacket() instead for proper buffering.
     *
     * @param string $data The received TCP data.
     * @return string The first packet's payload without TCP header.
     */
    public static function stripFirstTcpPacket(string $data): string
    {
        if (strlen($data) >= self::TCP_HEADER_SIZE && substr($data, 0, 4) === self::TCP_HEADER) {
            // Extract packet length from header (bytes 4-7, little-endian 32-bit)
            $lenBytes = substr($data, 4, 4);
            $packetLen = unpack('V', $lenBytes)[1];

            // Return only the first packet's payload
            $payloadStart = self::TCP_HEADER_SIZE;
            if ($payloadStart + $packetLen <= strlen($data)) {
                return substr($data, $payloadStart, $packetLen);
            } else {
                // Not enough data for full packet, return what we have
                return substr($data, $payloadStart);
            }
        }
        // No TCP header found, return as-is
        return $data;
    }

    /**
     * Debug logging for TCP troubleshooting.
     *
     * @param ZKTeco $self The ZKTeco instance.
     * @param string $message The debug message.
     */
    public static function debugLog(ZKTeco $self, string $message)
    {
        if (defined('ZKTECO_DEBUG') && ZKTECO_DEBUG) {
            $logFile = defined('ZKTECO_DEBUG_LOG') ? ZKTECO_DEBUG_LOG : storage_path('logs/zkteco_tcp_debug.log');
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] [{$self->_ip}:{$self->_port}] {$message}\n";
            @file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }

    /**
     * Encode a timestamp send at the timeclock
     * copied from zkemsdk.c - EncodeTime.
     *
     * @param string $t Format: "Y-m-d H:i:s"
     *
     * @return int
     */
    public static function encodeTime($t)
    {
        $timestamp = strtotime($t);
        $t = (object) [
            'year'   => (int) date('Y', $timestamp),
            'month'  => (int) date('m', $timestamp),
            'day'    => (int) date('d', $timestamp),
            'hour'   => (int) date('H', $timestamp),
            'minute' => (int) date('i', $timestamp),
            'second' => (int) date('s', $timestamp),
        ];

        $d = (($t->year % 100) * 12 * 31 + (($t->month - 1) * 31) + $t->day - 1) *
            (24 * 60 * 60) + ($t->hour * 60 + $t->minute) * 60 + $t->second;

        return $d;
    }

    public static function trimDeviceData($data, $command = '')
    {
        if (!$command || !$data) {
            return trim($data);
        }

        $result = str_replace($command.'=', '', $data);

        return trim($result);
    }

    /**
     * Decode a timestamp retrieved from the timeclock.
     *
     * @param int|string $t
     *
     * @return false|string Format: "Y-m-d H:i:s"
     */
    public static function decodeTime($t)
    {
        $second = floor($t % 60);
        $t = floor($t / 60);

        $minute = floor($t % 60);
        $t = floor($t / 60);

        $hour = floor($t % 24);
        $t = floor($t / 24);

        $day = floor($t % 31 + 1);
        $t = floor($t / 31);

        $month = floor($t % 12 + 1);
        $t = floor($t / 12);

        $year = floor($t + 2000);

        $d = date('Y-m-d H:i:s', strtotime(
            $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second
        ));

        return $d;
    }

    /**
     * @param string $hex
     *
     * @return string
     */
    public static function reverseHex($hex)
    {
        $tmp = '';

        for ($i = strlen($hex); $i >= 0; $i--) {
            $tmp .= substr($hex, $i, 2);
            $i--;
        }

        return $tmp;
    }

    /**
     * Checks a returned packet to see if it returned self::CMD_PREPARE_DATA,
     * indicating that data packets are to be sent
     * Returns the amount of bytes that are going to be sent.
     *
     * @param ZKTeco $self
     *
     * @return bool|number
     */
    public static function getSize(ZKTeco $self)
    {
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($self->_data_recv, 0, 8));
        $command = hexdec($u['h2'].$u['h1']);

        if ($command == self::CMD_PREPARE_DATA) {
            $u = unpack('H2h1/H2h2/H2h3/H2h4', substr($self->_data_recv, 8, 4));
            $size = hexdec($u['h4'].$u['h3'].$u['h2'].$u['h1']);

            return $size;
        } else {
            return false;
        }
    }

    /**
     * This function calculates the chksum of the packet to be sent to the
     * time clock
     * Copied from zkemsdk.c.
     */
    public static function createChkSum($p)
    {
        $l = count($p);
        $chksum = 0;
        $i = $l;
        $j = 1;
        while ($i > 1) {
            $u = unpack('S', pack('C2', $p['c'.$j], $p['c'.($j + 1)]));

            $chksum += $u[1];

            if ($chksum > self::USHRT_MAX) {
                $chksum -= self::USHRT_MAX;
            }
            $i -= 2;
            $j += 2;
        }

        if ($i) {
            $chksum = $chksum + $p['c'.strval(count($p))];
        }

        while ($chksum > self::USHRT_MAX) {
            $chksum -= self::USHRT_MAX;
        }

        if ($chksum > 0) {
            $chksum = -$chksum;
        } else {
            $chksum = abs($chksum);
        }

        $chksum -= 1;
        while ($chksum < 0) {
            $chksum += self::USHRT_MAX;
        }

        return pack('S', $chksum);
    }

    /**
     * This function puts a the parts that make up a packet together and
     * packs them into a byte string.
     */
    public static function createHeader($command, $chksum, $session_id, $reply_id, $command_string)
    {
        $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id).$command_string;

        $buf = unpack('C'.(8 + strlen($command_string)).'c', $buf);

        $u = unpack('S', self::createChkSum($buf));

        if (is_array($u)) {
            $u = reset($u);
        }
        $chksum = $u;

        $reply_id += 1;

        if ($reply_id >= self::USHRT_MAX) {
            $reply_id -= self::USHRT_MAX;
        }

        $buf = pack('SSSS', $command, $chksum, $session_id, $reply_id);

        return $buf.$command_string;
    }

    public static function makeCommKey(int $key, int $session_id, $ticks= 50) {

        $k = 0;
        for($i = 0; $i < 32; $i++) {
            if ($key & (1 << $i) ) {
                $k = ($k << 1 | 1 );
            } else {
                $k = $k << 1;
            }
        }
        $k += $session_id;
        $k = pack("I",$k);
        $k = unpack("C4", $k);

        $k = pack("C4",
            $k[1] ^ ord('Z'),
            $k[2] ^ ord('K'),
            $k[3] ^ ord('S'),
            $k[4] ^ ord('O')
        );

        $k = unpack('S2', $k); // S , n , v all in php for short int
        $k = pack('S2', $k[2], $k[1]);
        $B = 0xff & 50;
        $k = unpack('C4', $k);
        $k = pack('C4', $k[1] ^ $B, $k[2] ^ $B, $B, $k[4]^ $B);
        return $k;
    }

    /**
     * Checks a returned packet to see if it returned Util::CMD_ACK_OK,
     * indicating success.
     */
    static public function checkValid($reply)
    {
        if (empty($reply) || strlen($reply) < 8) {
            return false;
        }
        $u = unpack('H2h1/H2h2', substr($reply, 0, 8));

        $command = hexdec($u['h2'] . $u['h1']);
        switch($command) {
            case self::CMD_ACK_AUTH:
            case self::CMD_ACK_OK:
            case self::CMD_ACK_UNAUTH:
                return $command;
                break;
            default:
                return false;
        }
    }

    /**
     * Get User Role string.
     *
     * @param int $role
     *
     * @return string
     */
    public static function getUserRole($role)
    {
        switch ($role) {
            case self::LEVEL_USER:
                $ret = 'User';
                break;
            case self::LEVEL_ADMIN:
                $ret = 'Admin';
                break;
            default:
                $ret = 'Unknown';
        }

        return $ret;
    }

    /**
     * Get Attendance State string.
     *
     * @param int $state
     *
     * @return string
     */
    public static function getAttState($state)
    {
        switch ($state) {
            case self::ATT_STATE_FINGERPRINT:
                $ret = 'Fingerprint';
                break;
            case self::ATT_STATE_PASSWORD:
                $ret = 'Password';
                break;
            case self::ATT_STATE_CARD:
                $ret = 'Card';
                break;
            default:
                $ret = 'Unknown';
        }

        return $ret;
    }

    /**
     * Get Attendance Type string.
     *
     * @param int $type
     *
     * @return string
     */
    public static function getAttType($type)
    {
        switch ($type) {
            case self::ATT_TYPE_CHECK_IN:
                $ret = 'Check-in';
                break;
            case self::ATT_TYPE_CHECK_OUT:
                $ret = 'Check-out';
                break;
            case self::ATT_TYPE_BREAK_IN:
                $ret = 'Break-in';
                break;
            case self::ATT_TYPE_BREAK_OUT:
                $ret = 'Break-out';
                break;
            case self::ATT_TYPE_OVERTIME_IN:
                $ret = 'Overtime-in';
                break;
            case self::ATT_TYPE_OVERTIME_OUT:
                $ret = 'Overtime-out';
                break;
            default:
                $ret = 'Undefined';
        }

        return $ret;
    }

    /**
     * Receive data from device.
     *
     * @param ZKTeco $self
     * @param int    $maxErrors
     * @param bool   $first     if 'true' don't remove first 4 bytes for first row
     *
     * @return string
     */
    public static function recData(ZKTeco $self, $maxErrors = 10, $first = true)
    {
        $data = '';
        $bytes = self::getSize($self);
        $isTcp = property_exists($self, '_protocol') && $self->_protocol === 'tcp';

        if ($bytes) {
            $received = 0;
            $errors = 0;

            while ($bytes > $received) {
                $dataRec = '';
                if ($isTcp) {
                    $dataRec = self::readNextTcpPayload($self);
                } else {
                    $ret = @socket_recvfrom($self->_zkclient, $dataRec, 1032, 0, $self->_ip, $self->_port);
                }

                if (empty($dataRec)) {
                    if ($errors < $maxErrors) {
                        //try again if false
                        $errors++;
                        usleep(100000); // 100ms
                        continue;
                    } else {
                        //return empty if has maximum count of errors
                        self::logReceived($self, $received, $bytes);
                        unset($data);

                        return '';
                    }
                }

                // Reset error counter on successful read
                $errors = 0;

                if ($first === false) {
                    //The first 4 bytes don't seem to be related to the user
                    $dataRec = substr($dataRec, 8);
                }

                $data .= $dataRec;
                $received += strlen($dataRec);

                unset($dataRec);
                $first = false;
            }

            // Read the final CMD_ACK_OK / CMD_FREE_DATA packet that the device
            // sends after all data packets. This packet must be consumed here and
            // stored in _data_recv so the next _command() call can extract a valid
            // reply_id from it. Previously this code blindly cleared _tcp_buffer
            // and did a non-blocking recv, which on high-latency links (FRP proxy)
            // could either miss the ACK or discard it from the buffer, causing all
            // subsequent write commands to fail.
            if ($isTcp) {
                // The final ACK may already be sitting in _tcp_buffer (pulled in
                // by readNextTcpPayload's large 16384-byte recv). Try buffer first.
                $finalAck = '';
                $buffer = $self->_tcp_buffer;
                if (strlen($buffer) >= self::TCP_HEADER_SIZE) {
                    $extracted = self::extractTcpPacket($buffer);
                    if ($extracted !== null) {
                        list($finalAck, $remaining) = $extracted;
                        $self->_tcp_buffer = $remaining;
                    }
                }

                // If not in buffer, do a blocking recv (with the normal timeout)
                // to wait for it from the socket.
                if (empty($finalAck)) {
                    $self->_tcp_buffer = '';
                    $finalAck = self::recvData($self, 1024);
                }

                if (!empty($finalAck) && strlen($finalAck) >= 8) {
                    $self->_data_recv = $finalAck;
                    self::debugLog($self, 'recData final ACK: ' . bin2hex($finalAck));
                }
            } else {
                $dataRec = '';
                @socket_recvfrom($self->_zkclient, $dataRec, 1024, 0, $self->_ip, $self->_port);
                if (!empty($dataRec) && strlen($dataRec) >= 8) {
                    $self->_data_recv = $dataRec;
                }
            }
            unset($dataRec);
        }

        return $data;
    }

    /**
     * Read one complete TCP-framed ZKTeco payload from the socket.
     *
     * Accumulates data in $self->_tcp_buffer until a complete TCP frame
     * (PP\x82\x7d + 4-byte length + payload) can be extracted. This properly
     * handles partial TCP reads across multiple socket_recv calls without
     * losing buffered data.
     *
     * @param ZKTeco $self The ZKTeco instance.
     * @return string The extracted ZKTeco payload (TCP header stripped), or '' on failure.
     */
    private static function readNextTcpPayload(ZKTeco $self): string
    {
        $buffer = property_exists($self, '_tcp_buffer') ? $self->_tcp_buffer : '';
        $maxReadAttempts = 50; // Safety limit to prevent infinite loop
        $attempts = 0;

        while ($attempts < $maxReadAttempts) {
            // Try to extract a complete TCP packet from current buffer
            if (strlen($buffer) >= self::TCP_HEADER_SIZE) {
                $extracted = self::extractTcpPacket($buffer);
                if ($extracted !== null) {
                    list($payload, $remaining) = $extracted;
                    $self->_tcp_buffer = $remaining;
                    self::debugLog($self, 'recData extracted: ' . strlen($payload) . ' bytes, buffered=' . strlen($remaining));
                    return $payload;
                }
                // Buffer has TCP header but payload is incomplete - need more data
            }

            // Read more data from socket and accumulate in buffer
            $newData = '';
            $ret = @socket_recv($self->_zkclient, $newData, 16384, 0);

            if ($ret === false || $ret === 0 || empty($newData)) {
                // Socket timeout or error - save accumulated buffer for next call
                $self->_tcp_buffer = $buffer;
                self::debugLog($self, 'readNextTcpPayload: socket_recv returned no data, buffered=' . strlen($buffer));
                return '';
            }

            $buffer .= $newData;
            $attempts++;
            self::debugLog($self, 'readNextTcpPayload: read ' . strlen($newData) . ' bytes, total buffer=' . strlen($buffer));
        }

        // Safety: save buffer even if max attempts reached
        $self->_tcp_buffer = $buffer;
        self::debugLog($self, 'readNextTcpPayload: max attempts reached, buffered=' . strlen($buffer));
        return '';
    }

    /**
     * Strip all TCP headers from received data.
     * TCP data may contain multiple concatenated packets with headers.
     *
     * @param string $data The received TCP data.
     * @return string The payload without any TCP headers.
     */
    public static function stripAllTcpHeaders(string $data): string
    {
        $result = '';
        $pos = 0;
        $dataLen = strlen($data);

        while ($pos < $dataLen) {
            // Check if we have a TCP header at this position
            if ($pos + self::TCP_HEADER_SIZE <= $dataLen &&
                substr($data, $pos, 4) === self::TCP_HEADER) {
                // Extract packet length from header (bytes 4-7, little-endian 32-bit)
                $lenBytes = substr($data, $pos + 4, 4);
                $packetLen = unpack('V', $lenBytes)[1];

                // Skip the TCP header and extract the payload
                $payloadStart = $pos + self::TCP_HEADER_SIZE;
                if ($payloadStart + $packetLen <= $dataLen) {
                    $result .= substr($data, $payloadStart, $packetLen);
                    $pos = $payloadStart + $packetLen;
                } else {
                    // Not enough data, take what we have
                    $result .= substr($data, $payloadStart);
                    break;
                }
            } else {
                // No TCP header found, append remaining data as-is
                $result .= substr($data, $pos);
                break;
            }
        }

        return $result;
    }

    /**
     * @param ZKTeco $self
     * @param int    $received
     * @param int    $bytes
     */
    private static function logReceived(ZKTeco $self, $received, $bytes)
    {
        self::logger($self, 'Received: '.$received.' of '.$bytes.' bytes');
    }

    /**
     * Write log.
     *
     * @param ZKTeco $self
     * @param string $str
     */
    private static function logger(ZKTeco $self, $str)
    {
        if (defined('ZK_LIB_LOG')) {
            //use constant if defined
            $log = ZK_LIB_LOG;
        } elseif (function_exists('storage_path')) {
            // Use Laravel storage path if available
            $log = storage_path('logs/zkteco_error.log');
        } else {
            // Fallback to system temp directory
            $log = sys_get_temp_dir() . '/zkteco_error.log';
        }

        $row = '<'.$self->_ip.'> ['.date('d.m.Y H:i:s').'] ';
        $row .= (empty($self->_section) ? '' : '('.$self->_section.') ');
        $row .= $str;
        $row .= PHP_EOL;

        @file_put_contents($log, $row, FILE_APPEND);
    }

    /**
     * Check if the received data is a real-time event.
     *
     * @param string $data The received data.
     * @param int|null $eventMask Optional event mask to check against.
     * @return bool True if it's a real-time event.
     */
    public static function isRealTimeEvent(string $data, ?int $eventMask = null): bool
    {
        // Strip TCP header if present
        $payload = self::stripTcpHeader($data);

        if (strlen($payload) < 8) {
            return false;
        }

        // Read command ID (first 2 bytes, little-endian)
        $commandId = unpack('v', substr($payload, 0, 2))[1];

        if ($commandId !== self::CMD_REG_EVENT) {
            return false;
        }

        // Read event type (bytes 4-5, little-endian)
        $event = unpack('v', substr($payload, 4, 2))[1];

        // If no mask specified, check against all known events
        if ($eventMask === null) {
            $eventMask = self::EF_ATTLOG | self::EF_FINGER | self::EF_ENROLLUSER |
                         self::EF_ENROLLFINGER | self::EF_BUTTON | self::EF_UNLOCK |
                         self::EF_VERIFY | self::EF_FPFTR | self::EF_ALARM;
        }

        return ($event & $eventMask) !== 0;
    }

    /**
     * Get the event type from received data.
     *
     * @param string $data The received data.
     * @return int|null The event type constant or null if invalid.
     */
    public static function getEventType(string $data): ?int
    {
        $payload = self::stripTcpHeader($data);

        if (strlen($payload) < 8) {
            return null;
        }

        $commandId = unpack('v', substr($payload, 0, 2))[1];

        if ($commandId !== self::CMD_REG_EVENT) {
            return null;
        }

        return unpack('v', substr($payload, 4, 2))[1];
    }

    /**
     * Get human-readable event name from event type.
     *
     * @param int $eventType The event type constant.
     * @return string The event name.
     */
    public static function getEventName(int $eventType): string
    {
        $names = [
            self::EF_ATTLOG => 'attendance',
            self::EF_FINGER => 'finger',
            self::EF_ENROLLUSER => 'enroll_user',
            self::EF_ENROLLFINGER => 'enroll_finger',
            self::EF_BUTTON => 'button',
            self::EF_UNLOCK => 'unlock',
            self::EF_VERIFY => 'verify',
            self::EF_FPFTR => 'finger_feature',
            self::EF_ALARM => 'alarm',
        ];

        return $names[$eventType] ?? 'unknown';
    }

    /**
     * Decode a real-time attendance log from received data.
     *
     * @param string $data The received data (with or without TCP header).
     * @param string $deviceIp The device IP address.
     * @return array|null The decoded log entry or null if invalid.
     */
    public static function decodeRealTimeLog(string $data, string $deviceIp = ''): ?array
    {
        // Strip TCP header if present
        $payload = self::stripTcpHeader($data);

        if (strlen($payload) < 40) {
            return null;
        }

        // Skip the first 8 bytes (ZKTeco header)
        $recvData = substr($payload, 8);

        if (strlen($recvData) < 32) {
            return null;
        }

        // User ID: bytes 0-9 (ASCII string, null-terminated)
        $userId = rtrim(substr($recvData, 0, 9), "\x00");

        // Attendance time: bytes 26-32 (6 bytes: year, month, date, hour, minute, second)
        $timeData = substr($recvData, 26, 6);
        if (strlen($timeData) >= 6) {
            $year = 2000 + ord($timeData[0]);
            $month = ord($timeData[1]);
            $day = ord($timeData[2]);
            $hour = ord($timeData[3]);
            $minute = ord($timeData[4]);
            $second = ord($timeData[5]);

            $recordTime = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        } else {
            $recordTime = date('Y-m-d H:i:s');
        }

        // State/verification type: byte 24
        $state = ord(substr($recvData, 24, 1));

        return [
            'user_id' => $userId,
            'record_time' => $recordTime,
            'state' => $state,
            'device_ip' => $deviceIp,
        ];
    }

    /**
     * Decode any real-time event from received data.
     *
     * @param string $data The received data (with or without TCP header).
     * @param string $deviceIp The device IP address.
     * @return array|null The decoded event or null if invalid.
     */
    public static function decodeRealTimeEvent(string $data, string $deviceIp = ''): ?array
    {
        $eventType = self::getEventType($data);

        if ($eventType === null) {
            return null;
        }

        $payload = self::stripTcpHeader($data);
        $recvData = substr($payload, 8);

        $baseEvent = [
            'event_type' => $eventType,
            'event_name' => self::getEventName($eventType),
            'device_ip' => $deviceIp,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        switch ($eventType) {
            case self::EF_ATTLOG:
                $decoded = self::decodeRealTimeLog($data, $deviceIp);
                return $decoded ? array_merge($baseEvent, $decoded) : null;

            case self::EF_ENROLLUSER:
            case self::EF_VERIFY:
                // User events: user_id in first 9 bytes
                if (strlen($recvData) < 9) {
                    return null;
                }
                $userId = rtrim(substr($recvData, 0, 9), "\x00");
                return array_merge($baseEvent, [
                    'user_id' => $userId,
                ]);

            case self::EF_FINGER:
            case self::EF_ENROLLFINGER:
            case self::EF_FPFTR:
                // Fingerprint events: user_id + finger_index
                if (strlen($recvData) < 12) {
                    return null;
                }
                $userId = rtrim(substr($recvData, 0, 9), "\x00");
                $fingerIndex = ord(substr($recvData, 9, 1));
                return array_merge($baseEvent, [
                    'user_id' => $userId,
                    'finger_index' => $fingerIndex,
                ]);

            case self::EF_BUTTON:
                // Button press event
                if (strlen($recvData) < 4) {
                    return null;
                }
                $buttonId = unpack('v', substr($recvData, 0, 2))[1];
                return array_merge($baseEvent, [
                    'button_id' => $buttonId,
                ]);

            case self::EF_UNLOCK:
                // Door unlock event
                if (strlen($recvData) < 4) {
                    return null;
                }
                $doorId = ord(substr($recvData, 0, 1));
                $unlockType = ord(substr($recvData, 1, 1));
                return array_merge($baseEvent, [
                    'door_id' => $doorId,
                    'unlock_type' => $unlockType,
                ]);

            case self::EF_ALARM:
                // Alarm event
                if (strlen($recvData) < 4) {
                    return null;
                }
                $alarmType = unpack('v', substr($recvData, 0, 2))[1];
                return array_merge($baseEvent, [
                    'alarm_type' => $alarmType,
                ]);

            default:
                // Unknown event, return raw data
                return array_merge($baseEvent, [
                    'raw_data' => bin2hex($recvData),
                ]);
        }
    }
}
