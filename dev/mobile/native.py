"""Dedicated native Android Moodle app; reuses an existing SDK and system image."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import zipfile

ROOT = Path(__file__).resolve().parents[2]
RUNTIME = ROOT / 'dev/mobile/.runtime/android'
SDK = Path(os.environ.get('ACCOUNTING_ANDROID_SDK', str(Path.home() / 'Library/Android/sdk')))
NAME = 'AccountingMoodle'
SERIAL = 'emulator-5560'
PACKAGE = 'com.moodle.moodlemobile'
VERSION = '5.2.1'
APK_SHA256 = '96fdab4bc72376f4a766a1fe47f70e2306391ce5246cda7eff5486f697f8e61d'
APK = RUNTIME / 'moodle.apk'
APK_URL = 'https://packaging.moodle.org/desktop/android/moodlemobile-android-v521.apk'


def adb(*args, check=True):
    return subprocess.run(['adb', '-s', SERIAL, *args], check=check, text=True, capture_output=True)


def running():
    result = adb('get-state', check=False)
    if result.returncode or result.stdout.strip() != 'device':
        return False
    name = adb('emu', 'avd', 'name').stdout.splitlines()[0]
    if name != NAME:
        raise RuntimeError(f'{SERIAL} belongs to another emulator ({name}); refusing to modify it.')
    return True


def create_device():
    emulator = SDK / 'emulator/emulator'
    system = SDK / 'system-images/android-33/google_apis_playstore/arm64-v8a'
    if not emulator.is_file() or not (system / 'system.img').is_file():
        raise RuntimeError('Requires the existing ARM64 SDK emulator and Android 13 Google Play image. '
                           'Set ACCOUNTING_ANDROID_SDK if they are located elsewhere.')
    avdhome = RUNTIME / 'avd'
    device = avdhome / (NAME + '.avd')
    device.mkdir(parents=True, exist_ok=True)
    config = device / 'config.ini'
    if not config.exists():
        config.write_text('\n'.join([
            f'AvdId={NAME}', 'avd.ini.displayname=Accounting Moodle Native App',
            'avd.ini.encoding=UTF-8', 'abi.type=arm64-v8a', 'hw.cpu.arch=arm64',
            'hw.cpu.ncore=2', 'hw.ramSize=2048', 'disk.dataPartition.size=2G',
            'hw.lcd.width=1080', 'hw.lcd.height=1920', 'hw.lcd.density=420',
            'hw.keyboard=yes', 'hw.mainKeys=no', 'hw.gpu.enabled=yes', 'hw.gpu.mode=auto',
            'hw.camera.back=none', 'hw.camera.front=none', 'showDeviceFrame=no',
            'tag.id=google_apis_playstore', 'tag.display=Google Play', 'PlayStore.enabled=true',
            'target=android-33', f'image.sysdir.1={system}/',
        ]) + '\n')
    (avdhome / (NAME + '.ini')).write_text(f'avd.ini.encoding=UTF-8\npath={device}\ntarget=android-33\n')
    if not running():
        env = dict(os.environ, ANDROID_HOME=str(SDK), ANDROID_AVD_HOME=str(avdhome),
                   ANDROID_USER_HOME=str(RUNTIME / 'user'), ANDROID_EMULATOR_HOME=str(RUNTIME / 'emulator'))
        for directory in [RUNTIME / 'user', RUNTIME / 'emulator']:
            directory.mkdir(exist_ok=True)
        with (RUNTIME / 'emulator.log').open('a') as log:
            subprocess.Popen([str(emulator), '-avd', NAME, '-port', '5560', '-no-snapshot',
                              '-no-boot-anim', '-no-audio', '-camera-back', 'none', '-camera-front', 'none'],
                             env=env, stdout=log, stderr=log, start_new_session=True)
    print('Opening native Android emulator...', flush=True)
    deadline = time.monotonic() + 180
    while time.monotonic() < deadline:
        if adb('shell', 'getprop', 'sys.boot_completed', check=False).stdout.strip() == '1':
            return
        time.sleep(1)
    raise RuntimeError(f'Android did not finish booting. See {RUNTIME / "emulator.log"}')


def install():
    valid = False
    if APK.exists() and zipfile.is_zipfile(APK):
        with zipfile.ZipFile(APK) as archive:
            for name in archive.namelist():
                if name.endswith('assets/env.json'):
                    valid = json.loads(archive.read(name)).get('config', {}).get('versionname') == VERSION
    if not valid:
        subprocess.run(['curl', '-fL', '--retry', '2', APK_URL, '-o', str(APK)], check=True)
        if not zipfile.is_zipfile(APK):
            raise RuntimeError('Official APK download did not return an Android package.')
    digest = hashlib.sha256(APK.read_bytes()).hexdigest()
    if digest != APK_SHA256:
        raise RuntimeError('Official APK checksum changed; review the download before installing.')
    installed = adb('shell', 'dumpsys', 'package', PACKAGE, check=False).stdout
    if f'versionName={VERSION}' not in installed:
        print('Installing official Moodle Android app...', flush=True)
        result = adb('install', '-r', str(APK))
        print(result.stdout.strip(), flush=True)
    installed = adb('shell', 'dumpsys', 'package', PACKAGE).stdout
    if f'versionName={VERSION}' not in installed:
        raise RuntimeError('Installed app version does not match 5.2.1.')
    (RUNTIME / 'apk-sha256.txt').write_text(digest + '\n')


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else 'start'
    RUNTIME.mkdir(parents=True, exist_ok=True)
    if action == 'status':
        print('Native Android emulator running' if running() else 'Native Android emulator stopped')
        if running():
            print(adb('shell', 'dumpsys', 'package', PACKAGE).stdout.split('versionName=')[1].splitlines()[0])
        return
    if action == 'logs':
        print((RUNTIME / 'emulator.log').read_text()[-10000:])
        return
    if action == 'stop':
        if running():
            adb('emu', 'kill')
        return
    if action not in ['start', 'open', 'refresh']:
        raise RuntimeError('Usage: app.sh [start|open|refresh|status|stop|logs]')
    create_device()
    install()
    if action == 'refresh':
        adb('shell', 'pm', 'clear', PACKAGE)
    port = os.environ.get('MOODLE_PORT', '8080')
    adb('reverse', f'tcp:{port}', f'tcp:{port}')
    adb('shell', 'settings', 'put', 'global', 'device_provisioned', '1')
    adb('shell', 'settings', 'put', 'secure', 'user_setup_complete', '1')
    result = adb('shell', 'am', 'start', '-W', '-n', PACKAGE + '/.MainActivity')
    if 'Error' in result.stdout or 'Error' in result.stderr:
        raise RuntimeError(result.stdout + result.stderr)
    if not adb('shell', 'pidof', PACKAGE, check=False).stdout.strip():
        raise RuntimeError('Moodle did not stay running. Check the emulator logs.')
    print(f'Native Moodle {VERSION} is open in the Android emulator window.')
    print(f'In the App, enter site http://localhost:{port}')
    print('Login: appstudent / AppTest123!')
    print('Course: Accounting App Testing; quizzes: Accounting App DE / US')


if __name__ == '__main__':
    main()
