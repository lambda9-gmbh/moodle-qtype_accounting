{ pkgs }:
pkgs.importNpmLock.buildNodeModules {
  npmRoot = ./.;
  nodejs = pkgs.nodejs;
}
