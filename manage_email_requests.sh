
#!/bin/bash

# Find the PID of the running process
# PID=$(pgrep -f '/usr/bin/php /var/www/html/phpDaemons/CustomEmailPublisherDaemon/requests.php')
# PID=$(pgrep -f '/usr/bin/php /srv/CustomEmailPublisherDaemon/requests.php')

# if [ -z "$PID" ]; then
#     echo "Process is not running."

#     read -p "Do you want to start it? (y/n): " choice
#     if [ "$choice" == "y" ]; then
#         sudo /etc/init.emailpub start
#         tail -f /var/log/applications/customEmailPub.log
#     else
#         echo "Ignoring."
#     fi

#     exit 1
# fi

# echo "Stopping the process with PID: $PID"
# sudo kill -9 "$PID"
# echo "Process stopped successfully."


# read -p "Do you want to start it again? (y/n): " choice
# if [ "$choice" == "y" ]; then
#     sudo /etc/init.d/emailpub start
#     tail -f /var/log/applications/customEmailPub.log
# else
#     echo "Ignoring the start request."
# fi


#!/bin/bash

# Source the config.php file to import variables
source /srv/CustomEmailPublisherDaemon/config.php

# Find the PID of the running process
PID=$(pgrep -f "/usr/bin/php /srv/CustomEmailPublisherDaemon/$appExecutable")

if [ -z "$PID" ]; then
    echo "Process is not running."

    read -p "Do you want to start $appName? (y/n): " choice
    if [ "$choice" == "y" ]; then
        sudo /etc/init.d/"$appName" start
        tail -f $logLocation
    else
        echo "Ignoring."
    fi

    exit 1
fi

echo "Stopping the process with PID: $PID"
sudo kill -9 "$PID"
echo "Process stopped successfully."

read -p "Do you want to start $appName again? (y/n): " choice
if [ "$choice" == "y" ]; then
    sudo /etc/init.d/"$appName" start
    tail -f $logLocation
else
    echo "Ignoring the start request."
fi




