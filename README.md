# Oson модуль для Opencart 3

Модуль позволяет организовать оплату товаров в магазине через Oson.uz


## Донастройка системных настроек Opencart 3

1) Войти в панель админстратора.
2) Перейти во вкладку "Система", далее "Настройки"
3) в открывшемся окне с таблицей выбрать свой магазин и нажать на кнопку справа с изображением "карандаша"
4) сверху в окне перейти во вкладку "Сервер"
5) Напротив надписи "Включить ЧПУ" нажать по кнопке "Да" 
6) далее сохранить настройки нажав кнопку сверху справа с изображением "дискеты" 


## Установка модуля оплаты Oson Payment для Opencart 3:

1) Войти в панель админстратора.
2) Перейти во вкладку "Модули/Расширения" , далее "Установка расширений"
3) в открывшемся окне нажать кнопку "Загрузить"
4) в открывшемся окне выбрать файл установки "opencart-3-oson-payment-module.ocmod.zip"
5) После установки модуля произвести настройки  модуля согласно следующему разделу данной справки.


## Настройка модуля оплаты Oson Payment для Opencart 3:

1) Войти в панель админстратора.
2) Перейти во вкладку "Модули/Расширения", далее "Модули/Расширения"
3) в поле "Выберте тип расширения" выбрать "Оплата"
4) в открывшейся таблице найти строку Osongw, нажать на кнопку с иконкой "карандаш".
5) заполнить поля "Id магазина" (merchant_id), "Ключ магазина" (token), "Адрес сервера Oson" (адрес шлюза Oson, в данный момент https://api.oson.uz/api/invoice/)
6) способ оплаты "Оплата картой через Oson Payment"
7) в поле "Статус заказа после оплаты" выбрать "Ожидание"
8) далее сохранить настройки нажав кнопку сверху справа с изображением "дискеты" 
