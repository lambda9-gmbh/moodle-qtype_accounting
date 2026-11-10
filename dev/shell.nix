{ pkgs ? import <nixpkgs> { } }:
let
  php = pkgs.php83.withExtensions ({ enabled, all }:
    pkgs.lib.unique (enabled ++ (with all; [ gd intl mysqli pgsql soap zip ])));
in
pkgs.mkShell {
  packages = [
    php
    pkgs.php83Packages.composer
    pkgs.nodejs_22
    pkgs.python3
    pkgs.git
    pkgs.mariadb.client
    pkgs.postgresql
    pkgs.jdk17
  ] ++ pkgs.lib.optionals pkgs.stdenv.isLinux [ pkgs.chromium pkgs.chromedriver pkgs.glibcLocales ];
  shellHook = pkgs.lib.optionalString pkgs.stdenv.isLinux ''
    export LOCALE_ARCHIVE="${pkgs.glibcLocales}/lib/locale/locale-archive"
  '';
}
