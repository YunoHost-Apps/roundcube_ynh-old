#
# Common variables
#

# Roundcube version
VERSION=1.1.4

# Remote URL to fetch Roundcube complete tarball
ROUNDCUBE_COMPLETE_URL="https://downloads.sourceforge.net/project/roundcubemail/roundcubemail/${VERSION}/roundcubemail-${VERSION}-complete.tar.gz"

# Remote URL to fetch Roundcube source tarball
ROUNDCUBE_SOURCE_URL="https://github.com/roundcube/roundcubemail/releases/download/${VERSION}/roundcubemail-${VERSION}.tar.gz"

#
# Common helpers
#

# Print a message to stderr and exit
die() {
  printf "%s" "$1" 1>&2
  exit "${2:-1}"
}

# Download and install the app in a given directory with composer
install_with_composer() {
  DIR=$1

  # Retrieve and extract Roundcube tarball
  rc_tarball="${DIR}/roundcube.tar.gz"
  wget -q -O "$rc_tarball" "$ROUNDCUBE_SOURCE_URL" \
    || die "Unable to download Roundcube tarball"
  tar xf "$rc_tarball" -C "$DIR" --strip-components 1 \
    || die "Unable to extract Roundcube tarball"
  rm "$rc_tarball"

  # Install dependencies using composer
  cp ../sources/composer.json* "$DIR"
  curl -sS https://getcomposer.org/installer \
    | php -- --quiet --install-dir="$DIR" \
    || die "Unable to install Composer"
  (cd "$DIR" && php composer.phar install --quiet -n --no-dev) \
    || die "Unable to install Roundcube dependencies"

  # Install other dependencies manually
  cp -r ../sources/plugins/ldapAliasSync "${DIR}/plugins"
}
