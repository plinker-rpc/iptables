#!/bin/bash

#
# Post installs, Nginx+PHP-fpm, Plinker server and tasks runner.
#

#
set -e
export DEBIAN_FRONTEND=noninteractive
export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'
export HOME='/root'

#
# System Dependencys
setup_system() {
    #
    # set timezone
    echo "Europe/London" > /etc/timezone
    dpkg-reconfigure -f noninteractive tzdata >/dev/null 2>/dev/null
    #
    # Update System
    sudo apt-get update
    sudo apt-get -yq upgrade
    #
    # Install system packages
    sudo apt -qqy install fail2ban

    #
    ## IPTables Persistent
    #
    echo iptables-persistent iptables-persistent/autosave_v4 boolean true | sudo debconf-set-selections
    echo iptables-persistent iptables-persistent/autosave_v6 boolean true | sudo debconf-set-selections
    sudo apt -y install iptables-persistent
    #
    sudo iptables-save > /etc/iptables/rules.v4
    sudo iptables-save > /root/host-agent/iptables.rules.v4

    echo "#!/bin/sh
RESTORE=/sbin/iptables-restore
STAT=/usr/bin/stat
IPSTATE=/root/host-agent/iptables.rules.v4

test -x $RESTORE || exit 0
test -x $STAT || exit 0

# Check permissions and ownership rw------- for root
if test \`$STAT --format="%a" $IPSTATE\` -ne \"600\"; then
  echo \"Permissions for $IPSTATE must be 600 rw-------\"
  exit 0
fi

if test \`$STAT --format="%u" $IPSTATE\` -ne \"0\"; then
  echo \"The superuser must have ownership for $IPSTATE uid 0\"
  exit 0
fi

$RESTORE < $IPSTATE
" > /etc/network/if-pre-up.d/iptables

}

#
# Main 
#
main() {
    #
    setup_system
    #
    echo "Install finished."
}

main