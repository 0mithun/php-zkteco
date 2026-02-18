<p align="center"><a href="https://www.zkteco.com/" target="_blank"><img src="logo.jpg" width="400" alt="Zkteco Logo"></a></p>

<p align="center">
<a href="https://packagist.org/packages/0mithun/php-zkteco"><img src="https://img.shields.io/packagist/v/0mithun/php-zkteco.svg?style=flat-square" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/0mithun/php-zkteco"><img src="https://img.shields.io/packagist/dt/0mithun/php-zkteco.svg?style=flat-square" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/0mithun/php-zkteco"><img src="https://img.shields.io/packagist/l/0mithun/php-zkteco.svg?style=flat-square" alt="License"></a>
</p>

# PHP ZKTeco

A PHP library for interfacing with ZKTeco biometric attendance devices. Supports both **TCP** and **UDP** protocols for reliable communication with fingerprint attendance machines.

## Features

- **Dual Protocol Support**: Works with both TCP and UDP connections
- **User Management**: Add, remove, and retrieve users from the device
- **Attendance Logs**: Fetch attendance records with optional filtering
- **Real-Time Logs**: 🆕 Listen for live attendance events as they happen
- **Fingerprint Management**: Get, set, and remove fingerprints
- **Device Control**: Enable/disable device, restart, shutdown, sleep/resume
- **LCD Display**: Write custom messages to device screen
- **Voice Test**: Play voice prompts on the device
- **Laravel Integration**: Auto-discovery service provider included

## Requirements

- PHP >= 8.0
- PHP Sockets Extension (`ext-sockets`)

## Installation

```bash
composer require 0mithun/php-zkteco
```

## Quick Start

```php
use Mithun\PhpZkteco\Libs\ZKTeco;

// Create instance with TCP protocol (recommended for stability)
$zk = new ZKTeco('192.168.1.100', 4370, protocol: 'tcp');

// Or use UDP protocol (default)
$zk = new ZKTeco('192.168.1.100', 4370, protocol: 'udp');

// Connect to device
if ($zk->connect()) {
    // Get all users
    $users = $zk->getUsers();
    
    // Get attendance logs
    $attendance = $zk->getAttendances();
    
    // Disconnect when done
    $zk->disconnect();
}
```

## TCPMUX HTTP CONNECT Proxy

Connect through a TCPMUX httpconnect multiplexer for subdomain-based routing. This allows multiple devices to share a single port, routed by subdomain.

### Basic TCPMUX Usage

```php
use Mithun\PhpZkteco\Libs\ZKTeco;

$zk = new ZKTeco(
    host: 'device-1.proxy.example.com',  // subdomain.base_domain
    port: 4370,  // ZKTeco device port (default)
    tcpmux: [
        'subdomain' => 'device-1',
        'port' => 1337,  // TCPMUX proxy port
    ]
);

if ($zk->connect()) {
    $users = $zk->getUsers();
    $zk->disconnect();
}
```

### How TCPMUX Works

1. Library connects to proxy server at `base_domain:tcpmux_port`
2. Sends HTTP CONNECT request with target `subdomain.base_domain:device_port`
3. Proxy routes the connection to the device based on subdomain
4. After tunnel is established, ZKTeco protocol communicates through it

### Connection Comparison

| Feature | Direct TCP/UDP | TCPMUX |
|---------|---------------|--------|
| Port per device | Required (e.g., 7001, 7002...) | Shared (e.g., 1337) |
| Routing | By port number | By subdomain |
| Scalability | Limited by ports | Unlimited subdomains |

## Constructor Parameters

```php
$zk = new ZKTeco(
    host: '192.168.1.100',     // Device IP/hostname (required)
    port: 4370,                // Device port (default: 4370)
    shouldPing: false,         // Ping before connecting (default: false)
    timeout: 25,               // Connection timeout in seconds (default: 25)
    password: 0,               // Device password/CMD key (default: 0)
    protocol: 'tcp',           // Protocol: 'tcp' or 'udp' (default: 'udp')
    tcpmux: []                 // TCPMUX config (optional, see below)
);

// TCPMUX configuration array:
$tcpmux = [
    'subdomain' => 'device-1', // Device subdomain for routing
    'port' => 1337,            // FRP TCPMUX port
];
```

---

## API Reference

### Connection Methods

#### `connect(): bool`
Establishes connection to the ZKTeco device.

```php
$connected = $zk->connect();
if ($connected) {
    echo "Connected successfully!";
}
```

#### `disconnect(): bool`
Disconnects from the device.

```php
$zk->disconnect();
```

#### `ping(bool $throw = false): bool`
Tests connectivity to the device.

```php
if ($zk->ping()) {
    echo "Device is reachable";
}
```

---

### Device Information Methods

#### `vendorName(): string|false`
Returns the device manufacturer name.

```php
echo $zk->vendorName(); // "ZKTeco Inc."
```

#### `deviceName(): string|false`
Returns the device model name.

```php
echo $zk->deviceName(); // "K40"
```

#### `deviceId(): string|false`
Returns the device ID.

```php
echo $zk->deviceId(); // "1"
```

#### `serialNumber(): string|false`
Returns the device serial number.

```php
echo $zk->serialNumber(); // "PAS4234400018"
```

#### `version(): string|false`
Returns the firmware version.

```php
echo $zk->version(); // "Ver 6.60 Apr 13 2022"
```

#### `osVersion(): string|false`
Returns the OS version.

```php
echo $zk->osVersion(); // "1"
```

#### `platform(): string|false`
Returns the platform information.

```php
echo $zk->platform(); // "ZLM60_TFT"
```

#### `fmVersion(): string|false`
Returns the firmware version number.

```php
echo $zk->fmVersion(); // "10"
```

#### `pinWidth(): string|false`
Returns the PIN width setting.

```php
echo $zk->pinWidth(); // "14"
```

#### `workCode(): string|false`
Returns the work code status.

```php
echo $zk->workCode(); // "0"
```

#### `ssr(): string|false`
Returns the SSR (Self-Service Recorder) status.

```php
echo $zk->ssr(); // "1"
```

#### `faceFunctionOn(): string|false`
Returns whether face recognition is enabled.

```php
echo $zk->faceFunctionOn(); // "0" or "1"
```

#### `getMemoryInfo(): object|false`
Returns device memory information.

```php
$memory = $zk->getMemoryInfo();
// Returns: {adminCounts, userCounts, userCapacity, logCounts, logCapacity}
```

---

### Time Methods

#### `getTime(): string|false`
Returns the current device time.

```php
echo $zk->getTime(); // "2026-02-17 12:30:00"
```

#### `setTime(string $time): bool`
Sets the device time.

```php
$zk->setTime('2026-02-17 12:00:00');
```

---

### User Management Methods

#### `getUsers(?callable $callback = null): array`
Retrieves all registered users from the device.

```php
// Get all users
$users = $zk->getUsers();

// Each user contains:
// [
//     'uid' => 1,
//     'user_id' => '12345',
//     'name' => 'John Doe',
//     'role' => 0,
//     'password' => '1234',
//     'card_no' => '0000012345',
//     'device_ip' => '192.168.1.100'
// ]

// With filter callback
$admins = $zk->getUsers(function($user) {
    return $user['role'] == 14 ? $user : null;
});
```

#### `setUser(int $uid, string|int $userid, string $name, string|int $password, int $role = 0, int $cardno = 0): bool`
Adds or updates a user on the device.

**Parameters:**
- `$uid` - Unique ID (1-65535)
- `$userid` - User ID string (max 9 chars)
- `$name` - User name (max 24 chars)
- `$password` - Password (max 8 chars)
- `$role` - Role: 0=User, 14=Admin (default: 0)
- `$cardno` - Card number (default: 0)

```php
// Add a regular user
$zk->setUser(1, '10001', 'John Doe', '1234', 0, 0);

// Add an admin user
$zk->setUser(2, '10002', 'Admin User', '5678', 14, 0);
```

#### `removeUser(int $uid): bool`
Removes a user by UID.

```php
$zk->removeUser(1);
```

#### `deleteUsers(callable $callback): void`
Removes users conditionally using a callback.

```php
// Delete all users with role 0
$zk->deleteUsers(function($user) {
    return $user['role'] == 0;
});
```

#### `clearAllUsers(): bool`
⚠️ **Warning:** Removes ALL users from the device.

```php
$zk->clearAllUsers();
```

#### `clearAdminPriv(): bool`
Removes admin privileges from all users.

```php
$zk->clearAdminPriv();
```

---

### Attendance Methods

#### `getAttendances(?callable $callback = null): array`
Retrieves attendance records from the device.

```php
// Get all attendance records
$logs = $zk->getAttendances();

// Each record contains:
// [
//     'uid' => 1,
//     'user_id' => 12345,
//     'state' => 1,
//     'record_time' => '2026-02-17 09:00:00',
//     'type' => 0,
//     'device_ip' => '192.168.1.100'
// ]

// With filter callback (e.g., today's records only)
$todayLogs = $zk->getAttendances(function($record) {
    if (str_starts_with($record['record_time'], date('Y-m-d'))) {
        return $record;
    }
    return null;
});
```

**Attendance States:**
- `0` - Check In
- `1` - Check Out
- `2` - Break Out
- `3` - Break In
- `4` - OT In
- `5` - OT Out

#### `clearAttendance(): bool`
⚠️ **Warning:** Clears ALL attendance logs from the device.

```php
$zk->clearAttendance();
```

#### `getRealTimeLogs(callable $callback, int $timeout = 0): bool`
🆕 Registers for real-time attendance events. When a user scans their fingerprint or enters credentials, the callback is called immediately with the log data.

**Parameters:**
- `$callback` - Function called with each real-time log entry
- `$timeout` - Timeout in seconds (0 = infinite, default)

```php
// Listen for real-time attendance events
$zk->getRealTimeLogs(function($log) {
    echo "User {$log['user_id']} punched at {$log['record_time']}\n";
    
    // $log contains:
    // [
    //     'user_id' => '12345',
    //     'record_time' => '2026-02-17 09:00:00',
    //     'state' => 1,
    //     'device_ip' => '192.168.1.100'
    // ]
}, timeout: 60); // Listen for 60 seconds

// Or listen indefinitely (Ctrl+C to stop)
$zk->getRealTimeLogs(function($log) {
    // Process attendance in real-time
    saveToDatabase($log);
});
```

**Note:** This method blocks while listening for events. Use the `$timeout` parameter or run in a background process/worker.

#### `getRealTimeEvents(callable $callback, int $events, int $timeout = 0): bool`
🆕 **v1.2.0** Registers for multiple real-time event types. This is a more comprehensive version of `getRealTimeLogs()` that supports all device events.

**Supported Events (use `Mithun\PhpZkteco\Libs\Services\Util` constants):**

| Constant | Value | Description |
|----------|-------|-------------|
| `EF_ATTLOG` | 1 | Attendance log (user punch in/out) |
| `EF_FINGER` | 2 | Fingerprint scanned |
| `EF_ENROLLUSER` | 4 | User enrolled on device |
| `EF_ENROLLFINGER` | 8 | Fingerprint enrolled |
| `EF_BUTTON` | 16 | Button pressed on device |
| `EF_UNLOCK` | 32 | Door unlocked |
| `EF_VERIFY` | 128 | User verified |
| `EF_FPFTR` | 256 | Fingerprint feature event |
| `EF_ALARM` | 512 | Alarm triggered |

**Parameters:**
- `$callback` - Function called with each event
- `$events` - Bitmask of events to listen for (combine with `|`)
- `$timeout` - Timeout in seconds (0 = infinite)

```php
use Mithun\PhpZkteco\Libs\Services\Util;

// Listen for attendance AND user enrollment events
$events = Util::EF_ATTLOG | Util::EF_ENROLLUSER | Util::EF_ENROLLFINGER;

$zk->getRealTimeEvents(function($event) {
    echo "Event: {$event['event_name']}\n";
    
    switch ($event['event_type']) {
        case Util::EF_ATTLOG:
            echo "Attendance: User {$event['user_id']} at {$event['record_time']}\n";
            break;
            
        case Util::EF_ENROLLUSER:
            echo "New user enrolled: {$event['user_id']}\n";
            break;
            
        case Util::EF_ENROLLFINGER:
            echo "Fingerprint enrolled for user {$event['user_id']}, finger {$event['finger_index']}\n";
            break;
            
        case Util::EF_UNLOCK:
            echo "Door {$event['door_id']} unlocked\n";
            break;
            
        case Util::EF_ALARM:
            echo "Alarm triggered: type {$event['alarm_type']}\n";
            break;
    }
}, $events, timeout: 3600); // Listen for 1 hour
```

**Event Data Structure:**

All events include these base fields:
```php
[
    'event_type' => 1,              // Event constant (EF_ATTLOG, etc.)
    'event_name' => 'attendance',   // Human-readable name
    'device_ip' => '192.168.1.100',
    'timestamp' => '2026-02-17 09:00:00',
]
```

Plus event-specific fields:

| Event | Additional Fields |
|-------|-------------------|
| `EF_ATTLOG` | `user_id`, `record_time`, `state` |
| `EF_ENROLLUSER` / `EF_VERIFY` | `user_id` |
| `EF_FINGER` / `EF_ENROLLFINGER` / `EF_FPFTR` | `user_id`, `finger_index` |
| `EF_BUTTON` | `button_id` |
| `EF_UNLOCK` | `door_id`, `unlock_type` |
| `EF_ALARM` | `alarm_type` |

---

### Fingerprint Methods

#### `getFingerprint(int $uid): array|false`
Retrieves fingerprint data for a user.

```php
$fingerprints = $zk->getFingerprint(1);
// Returns array of fingerprint templates indexed by finger ID (0-9)
```

#### `setFingerprint(int $uid, array $fingerprint): bool`
Sets fingerprint data for a user.

```php
$zk->setFingerprint(1, $fingerprintData);
```

#### `removeFingerprint(int $uid, array $fingerIds): int`
Removes specific fingerprints from a user.

```php
// Remove fingerprints 0 and 1 from user with UID 1
$deleted = $zk->removeFingerprint(1, [0, 1]);
echo "Deleted $deleted fingerprints";
```

---

### Device Control Methods

#### `disableDevice(): bool`
Disables the device (prevents user interaction).

```php
$zk->disableDevice();
// Device screen shows "Working..." and ignores user input
```

#### `enableDevice(): bool`
Re-enables the device.

```php
$zk->enableDevice();
```

#### `shutdown(): bool`
Powers off the device.

```php
$zk->shutdown();
```

#### `restart(): bool`
Restarts the device.

```php
$zk->restart();
```

#### `sleep(): bool`
Puts the device into sleep mode.

```php
$zk->sleep();
```

#### `resume(): bool`
Wakes the device from sleep mode.

```php
$zk->resume();
```

---

### Display & Audio Methods

#### `writeLCD(string $message = 'Welcome ZkTeco'): bool`
Displays a message on the device LCD screen.

```php
$zk->writeLCD('Hello World!');
```

#### `clearLCD(): bool`
Clears the LCD screen.

```php
$zk->clearLCD();
```

#### `testVoice(int $index = 0): bool`
Plays a voice prompt on the device.

```php
$zk->testVoice(0);  // Play voice prompt index 0

// Common voice indices:
// 0 - "Thank you"
// 1 - "Incorrect fingerprint"
// 4 - "Thank you"
```

---

### Custom Data Methods

#### `getDeviceData(string $key): string`
Retrieves device configuration data.

```php
echo $zk->getDeviceData('TCPPort');     // "4370"
echo $zk->getDeviceData('DeviceID');    // "1"
```

#### `setCustomData(string $key, mixed $value): bool`
Sets custom data on the device.

```php
$zk->setCustomData('my_company', 'Acme Corp');
```

#### `getCustomData(string $key): string`
Retrieves custom data from the device.

```php
echo $zk->getCustomData('my_company'); // "Acme Corp"
```

#### `setPushCommKey(string $value): bool`
Sets the push communication key (for iClock integration).

```php
$zk->setPushCommKey('my_secret_key');
```

#### `getPushCommKey(): string`
Gets the push communication key.

```php
echo $zk->getPushCommKey();
```

---

## Protocol Comparison

| Feature | TCP | UDP |
|---------|-----|-----|
| Reliability | High | Medium |
| Speed | Slightly slower | Fast |
| Connection | Persistent | Connectionless |
| Large data transfer | Better | May have issues |
| Recommended for | Production | Local testing |

## Error Handling

```php
try {
    $zk = new ZKTeco('192.168.1.100', 4370, protocol: 'tcp');
    
    if (!$zk->connect()) {
        throw new Exception('Failed to connect to device');
    }
    
    $users = $zk->getUsers();
    
    $zk->disconnect();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Laravel Usage

The package auto-registers its service provider. You can use it in your controllers or commands:

```php
use Mithun\PhpZkteco\Libs\ZKTeco;

class AttendanceController extends Controller
{
    public function sync()
    {
        $zk = new ZKTeco(
            config('zkteco.ip'),
            config('zkteco.port'),
            protocol: config('zkteco.protocol', 'tcp')
        );
        
        if ($zk->connect()) {
            $attendances = $zk->getAttendances();
            
            foreach ($attendances as $record) {
                // Save to database
                Attendance::updateOrCreate(
                    ['uid' => $record['uid'], 'record_time' => $record['record_time']],
                    $record
                );
            }
            
            $zk->disconnect();
        }
        
        return response()->json(['synced' => count($attendances)]);
    }
}
```

## Debugging

Enable debug logging by defining constants before instantiation:

```php
define('ZKTECO_DEBUG', true);
define('ZKTECO_DEBUG_LOG', '/path/to/debug.log');

$zk = new ZKTeco('192.168.1.100', 4370, protocol: 'tcp');
```

## Contributing

Please see [CONTRIBUTING](https://github.com/0mithun/php-zkteco/graphs/contributors) for details.

## Security

If you've found a security vulnerability, please email [mithunrptc@gmail.com](mailto:mithunrptc@gmail.com) instead of using the issue tracker.

## Credits

- [Mithun](https://github.com/0mithun)
- [ZK Protocol Documentation](https://github.com/adrobinoga/zk-protocol)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

