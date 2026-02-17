<?php

namespace Mithun\PhpZkteco\Libs\Services;

use Mithun\PhpZkteco\Libs\ZKTeco;
use ErrorException;
use Exception;

class Connect
{
    /**
     * Establishes a connection with the ZKTecoPhp device.
     *
     * @param ZKTeco $self The instance of the ZKTecoPhp class.
     *
     * @return bool Returns true if the connection is successfully established, false otherwise.
     */
    public static function connect(ZKTeco $self)
    {
        // ping to device
        Ping::run($self);

        // Set the current section of the code.
        $self->_section = __METHOD__;

        // Define command and other necessary variables.
        $command = Util::CMD_CONNECT;
        $command_string = '';
        $chksum = 0;
        $session_id = 0;
        $reply_id = -1 + Util::USHRT_MAX;

        // Create the header for the command.
        $buf = Util::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        // Send the command to the ZKTecoPhp device using protocol-aware helper.
        Util::sendData($self, $buf);

        try {
            // Attempt to receive data from the device using protocol-aware helper.
            $self->_data_recv = Util::recvData($self, 1024);

            // If data is received, process it.
            if (strlen($self->_data_recv) > 0) {
                $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($self->_data_recv, 0, 8));

                $session = hexdec($u['h6'] . $u['h5']);
                if (empty($session)) {
                    return false;
                }
                $self->_session_id = $session;
                $result = Util::checkValid($self->_data_recv);
                if ($result == Util::CMD_ACK_UNAUTH) {
                    $command_string = Util::makeCommKey($self->_password, $self->_session_id);
                    $buf = Util::createHeader(Util::CMD_ACK_AUTH, 0, $self->_session_id, $reply_id, $command_string);
                    Util::sendData($self, $buf);
                    $self->_data_recv = Util::recvData($self, 1024);
                    if (strlen($self->_data_recv) > 0) {
                        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($self->_data_recv, 0, 8));
                        $session = hexdec($u['h6'].$u['h5']);
                        if (empty($session)) {
                            return false;
                        }

                        $result = Util::checkValid($self->_data_recv);
                        if($result == Util::CMD_ACK_UNAUTH){
                            return false;
                        }
                        $self->_session_id = $session;
                    }
                }
                return $result;
            } else {
                return false;
            }
        } catch (ErrorException $e) {
            // Catch any error exceptions and return false.
            return false;
        } catch (Exception $e) {
            // Catch any general exceptions and return false.
            return false;
        }
    }

    /**
     * Disconnects from the ZKTecoPhp device.
     *
     * @param ZKTeco $self The instance of the ZKTecoPhp class.
     *
     * @return bool Returns true if the disconnection is successful, false otherwise.
     */
    public static function disconnect(ZKTeco $self)
    {
        if (!Ping::run($self)) {
            return true;
        }

        // Set the current section of the code.
        $self->_section = __METHOD__;

        // Define command and other necessary variables.
        $command = Util::CMD_EXIT;
        $command_string = '';
        $chksum = 0;
        $session_id = $self->_session_id;

        // Unpack the data received during connection to extract reply ID.
        // Guard against empty or insufficient data
        $reply_id = 0;
        if (strlen($self->_data_recv) >= 8) {
            $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($self->_data_recv, 0, 8));
            $reply_id = hexdec($u['h8'].$u['h7']);
        }

        // Create the header for the command.
        $buf = Util::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        // Send the command to the ZKTecoPhp device using protocol-aware helper.
        Util::sendData($self, $buf);

        try {
            // Attempt to receive data from the device using protocol-aware helper.
            $self->_data_recv = Util::recvData($self, 1024);

            // Reset the session ID in the ZKTecoPhp instance.
            $self->_session_id = 0;

            // Check if the received data is valid.
            return Util::checkValid($self->_data_recv);
        } catch (ErrorException $e) {
            // Catch any error exceptions and return false.
            return false;
        } catch (Exception $e) {
            // Catch any general exceptions and return false.
            return false;
        }
    }
}
