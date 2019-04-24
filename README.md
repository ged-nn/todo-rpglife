# todo-rpglife
Gaming for todo list as RPG

[Home page todo-rpglife](https://github.com/ged-nn/todo-rpglife)

## 23.04.2019 00:26:06
### readme.md

[Описание формата файла](http://webdesign.ru.net/article/pravila-oformleniya-fayla-readmemd-na-github.html)

## 24.04.2019 22:33:28
### robot.php
    * todo_list - поправил вывод. Стало более аккуратно
    * todo x - через классы сделано
    * todo x1,2,3 - можно закрывать сразу несколько задач
    * todo x1 комментарий - будет добавляться комментарий к закрытию задачи

### task.php
    + Перенес класс задач в [этот файл](task.php). Будем надеяться, что так читаемее
    + Task->recurrence
    + Task->setComplete
    + Task_List->load
    + Task_List->save
    
### scoring.php
    - Убрал классы Task_List и Task

## 23.04.2019 00:05:14
### robot.php
    + Вывод таблицы с распределением очков по проектам и контекстам
    * Вытащил все регистрационные данные в файл конфигурации
    + обрабатываем очки из массива
    
### scoring.php
    * все очки записываем в массив $_score
    * возвращаем очки массивом
    * array_add - возвращает сортированный массив

## 22.04.2019 22:48:53
* git log
* git reset --hard ID
* git push --force
	
## 2019/04/22 22:25:05
Что-то залили на GitHub. Пока все очень страшно, но начало положено.
