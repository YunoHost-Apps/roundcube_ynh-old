#
# Common variables
#

# Roundcube version
VERSION=1.1.4

# Roundcube complete tarball checksum
ROUNDCUBE_COMPLETE_MD5="260686b4894896744bffa2d8bb259995"

# Remote URL to fetch Roundcube complete tarball
ROUNDCUBE_COMPLETE_URL="https://downloads.sourceforge.net/project/roundcubemail/roundcubemail/${VERSION}/roundcubemail-${VERSION}-complete.tar.gz"

# App package root directory should be the parent folder
PKGDIR=$(cd ../; pwd)

#
# Common helpers
#

# Print a message to stderr and exit
# usage: print MSG [RETCODE]
die() {
  printf "%s" "$1" 1>&2
  exit "${2:-1}"
}

# Download and extract Roundcube sources to the given directory
# usage: extract_roundcube_to DESTDIR
extract_roundcube() {
  local DESTDIR=$1

  # retrieve and extract Roundcube tarball
  rc_tarball="${DESTDIR}/roundcube.tar.gz"
  wget -q -O "$rc_tarball" "$ROUNDCUBE_COMPLETE_URL" \
    || die "Unable to download Roundcube tarball"
  echo "$ROUNDCUBE_COMPLETE_MD5 $rc_tarball" | md5sum -c >/dev/null \
    || die "Invalid checksum of downloaded tarball"
  tar xf "$rc_tarball" -C "$DESTDIR" --strip-components 1 \
    || die "Unable to extract Roundcube tarball"
  rm "$rc_tarball"

  # TODO: apply patches
}

# Execute a composer command from a given directory
# usage: composer_exec WORKDIR AS_USER COMMAND [ARG ...]
exec_composer() {
  local WORKDIR=$1
  local AS_USER=$2
  shift 2

  if [[ $AS_USER = $(whoami) ]]; then
    php "${WORKDIR}/composer.phar" $@ \
      -d "${WORKDIR}" --quiet --no-interaction
  else
    # use sudo twice to be root and be allowed to use another user
    sudo sudo -u "$AS_USER" php "${WORKDIR}/composer.phar" $@ \
      -d "${WORKDIR}" --quiet --no-interaction
  fi
}

# Install and initialize Composer in the given directory
# usage: init_composer DESTDIR
init_composer() {
  local DESTDIR=$1

  # install composer
  curl -sS https://getcomposer.org/installer \
    | php -- --quiet --install-dir="$DESTDIR" \
    || die "Unable to install Composer"

  # copy composer.json for Roundcube with complete dependencies
  cp "${PKGDIR}/conf/rc-complete_composer.json" "${DESTDIR}/composer.json"

  # update dependencies to create composer.lock
  exec_composer "$DESTDIR" admin update --no-dev \
    || die "Unable to update Roundcube core dependencies"
}
