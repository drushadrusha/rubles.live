# 💸 rubles.live

## ⚙️ как работает?
это парсер курсов валют для rubles.live.

скрипт сохраняет курсы каждой страны в формате json в директорию с двухбуквенным кодом страны.

## 🚙 рекваерментс
* ~ PHP 8.2.5
* нужно закинуть [simple_html_dom.php](http://sourceforge.net/projects/simplehtmldom/) в директорию со скриптом
* выставить в переменную ```$storage_path``` директорию куда будут сохранятся курсы
* создать по пути ```$storage_path``` директории ```data```, ```am```, ```tr```, ```vn``` и так далее.
* запустить скрипт и надеяться на лучшее

## 🤔 примечания
* все страны за исключением казахстана имеют название директории соответсвующее своему двухбуквенному iso коду. казахстан же сохраняется в директорию ```data```