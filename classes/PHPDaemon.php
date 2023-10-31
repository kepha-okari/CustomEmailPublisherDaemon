<?php
require_once("DatabaseUtilities.php");
require_once("WorkerException.php");
require_once "PepipostEmailSender.php";

/**
 * Script to process something.
 */
class PHPDaemon
{
    /**
     * Constructor.
     */
    function __construct()
    {
        // Initialise things here
    }

    /**
     * Processes something.
     *
     * @param object $mysqli MySQL connection object
     * @param string $log Path to the log file
     *
     * @return void
     */
    public function process($mysqli, $log)
    {
        try {
            error_log("INFO :: " . date("y-m-d H:i:s") . " Connecting to RabbitMQ" . "\n", 3, $log);
            $connection = new AMQPStreamConnection('172.17.0.2', 5672, 'guest', 'guest');
            $channel = $connection->channel();
            // $CHANNEL_NAME = "send_bulk_email_exchange";
            $CHANNEL_NAME = "sendBulkEmailsExchange";
            $channel->exchange_declare($CHANNEL_NAME, 'topic', false, true, false);

            error_log("INFO :: " . date("y-m-d H:i:s") . " CONNECTED to RabbitMQ" . "\n", 3, $log);

            if ($mysqli->connect_errno) {
                throw new Exception("Failed to connect to MySQL: " . $mysqli->connect_error);
            }

            error_log("INFO :: " . date("y-m-d H:i:s") . " About to get the unprocessed sms blasts." . "\n", 3, $log);

            $mysqli->begin_transaction();

            $query = "SELECT * FROM outgoing_bulk_emails WHERE status = 1 LIMIT 5";
            $result = DatabaseUtilities::executeQuery($mysqli, $query);

            if (count($result) > 0) {
                $count = 0;
                $dataList = []; // Prepare an array to hold all data arrays
                $idsToUpdate = []; // Prepare an array to hold the IDs for updating status

                error_log("INFO :: " . date("y-m-d H:i:s") . " Preparing objects for queueing\n", 3, $log);


                foreach ($result as $row) {
                    $dataList[] = [
                        "id" => $row['id'],
                        "message" => $row['message'],
                        "api_key" => $row['api_key'],
                        "bulk_blast_id" => $row['bulk_blast_id'],
                        "apiClientID" => $row['client_id'],
                        "service_id" => $row['service_id'],
                        "email_from" => $row['email_from'],
                        "email_to" => $row['email_to'],
                        "email_subject" => $row['email_subject'],
                        "status" => $row['status'],
                        "delivery_status" => $row['delivery_status'],
                        "serviceID" => $row['service_id']
                    ];
                    $idsToUpdate[] = $row["id"];
                }

     
                $routingKey = 'topic.key';
                $numRows = count($dataList);

                // Publish messages to RabbitMQ
                try {
                    $channel->tx_select(); // Start RabbitMQ transaction

                    foreach ($dataList as $data) {
                        $msgData = json_encode($data);
                        // Log the processed message
                        error_log("INFO :: " . date("y-m-d H:i:s") . " [•] RECEPIENT: " . $data['email_to'] . "  MSG: " . substr($data['message'], 0, 4) . " SUBJECT: " . $data['email_subject'] . "\n\n", 3, $log);

                        $msg = new AMQPMessage($msgData, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
                        $channel->basic_publish($msg, $CHANNEL_NAME, $routingKey);
                        $count++;

                        // Batch update the status
                        error_log("INFO :: " . date("y-m-d H:i:s") . " [***] [•] Flagged ".$data['id']." as processed. [•] [***] \n", 3, $log);

                        $updateQuery = "UPDATE outgoing_bulk_emails SET status = 2 WHERE id = ". $data['id'];
                        DatabaseUtilities::executeQuery($mysqli, $updateQuery);

                        // Log the processed message
                        // error_log("INFO :: " . date("y-m-d H:i:s") . " [•] PHONE: " . $data['msisdn'] . "  MSG: " . substr($data['message'], 0, 5) . " SENDER_ID: " . $data['serviceID'] . " CLIENT: " . $data['apiClientID'] . "  ID: " . $data['id'] . "\n\n", 3, $log);
                    }

                    $channel->tx_commit(); // Commit RabbitMQ transaction
                    $mysqli->commit(); // Commit MySQL transaction

                    error_log("INFO :: " . date("y-m-d H:i:s") . " Processed " . $count . " logged requests\n", 3, $log);
                } catch (Exception $e) {
                    $channel->tx_rollback(); // Rollback RabbitMQ transaction
                    $mysqli->rollback(); // Rollback MySQL transaction

                    throw $e; // Re-throw the exception
                }
            } else {
                error_log("INFO :: " . date("y-m-d H:i:s") . " No records found \n", 3, $log);
            }

            $channel->close();
            error_log("INFO :: " . date("y-m-d H:i:s") . " CLOSED RabbitMQ channel::(" . $CHANNEL_NAME . ")\n", 3, $log);

            $connection->close();
            error_log("INFO :: " . date("y-m-d H:i:s") . " CLOSED RabbitMQ\n", 3, $log);

        } catch (Exception $e) {
            error_log($e->getMessage());
            echo "An error occurred. Please try again later.";
        }
    }
}
// /srv/olivemobile/bongasms/app/v1/daemons/new_apps/php/bongaCustomPublisher/bongaCustomBroadcasts.php
// $batchIDs = [];
//     foreach ($attibutes as $key => $value ) {
//         $batchIDs [] = [ $key => $value ];
// }

?>
