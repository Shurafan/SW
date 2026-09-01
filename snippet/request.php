<?php
// Отвечаем ТОЛЬКО на ajax запросы
//if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) die('stop');
$action = filter_input(INPUT_POST,'action');
if (empty($action))  die('stop');
// А если есть - работаем
switch ($action) {
  //case 'next': 
   // $invet = (is_null($_SERVER['HTTP_REFERER'])) ? filter_input(INPUT_POST,'invet') : trim(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH), '/');
   // list($catalog,$alias) = explode("/", $invet);
    // здесь проверку разрешонных каталогов нужно сделать
    // $parent = $modx->getObject('modResource',array('published' => 1,'alias' => $catalog));
    // if ($parent==0) die('stop');
   //  $children = $parent->getMany('Children');
    // if(empty($children)) die('stop');
    //нужно поправить сортировку menuindex
    //uasort($children, 'cmp');
    //if($alias){//Пробигаемся по потомкам 
    //    foreach ($children as $id=>$val) {
     //       if(!empty($ri)) {$res =otvet($catalog,$val);
    //           break;
    //        }
    //      if($val->alias==$alias) $ri= true;
    //    } 
    //}
    //else {
    //    reset($children);
    //    $res=otvet($catalog,current($children));
    //}
    
    case 'next': 
    $invet = $_SERVER['HTTP_REFERER'] ?? filter_input(INPUT_POST, 'invet');
    $path = trim(parse_url($invet, PHP_URL_PATH), '/');
    [$catalog, $alias] = array_pad(explode('/', $path), 2, '');
    
    $parent = $modx->getObject('modResource', ['published' => 1, 'alias' => $catalog]);
    if (!$parent) { echo 'stop'; exit; }
    
    $children = $parent->getMany('Children');
    if (empty($children)) { echo 'stop'; exit; }
    
    // Сортировка
    uasort($children, 'cmp');
    
    $res = null; // Обязательно инициализируем
    $found = false; // Флаг: нашли ли мы текущий элемент
    
    if ($alias) {
        foreach ($children as $child) {
            // Если флаг поднят, значит предыдущий элемент был искомым. 
            // Текущий $child — это следующий элемент. Отдаем его.
            if ($found) {
                $res = otvet($catalog, $child);
                break;
            }
            // Поднимаем флаг, если нашли совпадение по алиасу
            if ($child->get('alias') === $alias) {
                $found = true;
            }
        }
        // Если цикл закончился, а $res все еще пуст (был последний элемент или не нашли)
        // Можно добавить логику зацикливания или возврата ошибки, но пока просто ничего не делаем.
    } else {
        // Если алиас не передан — отдаем первый элемент
        reset($children);
        $first = current($children);
        if ($first) {
            $res = otvet($catalog, $first);
        }
    }
    
    // Явно выводим результат или stop
    // echo $res ?? 'stop';
    // break; // Выход из switch
    
    break;
  case 'pay_init':
  case 'pay_result':
  case 'invoice':
  case 'pay_notify':
    // PaymentHandler сам отдаёт ответ и делает exit
    include $modx->getOption('base_path') . 'assets/teme_sw/snippet/PaymentHandler.php';
    exit;

  case 'stendMail':
    if($_POST['mail']) die('stop');
    if (!empty($_FILES['file']['tmp_name'])) {
	    $path = $modx->getOption('base_path')."/assets/attach/".$_FILES['file']['name']; 
	    if (copy($_FILES['file']['tmp_name'], $path)){
	    	$file_name = $_FILES['file']['name'];
	    }
    }
    $memail = 'shurafan@yandex.ru';
    $name = ($_POST['name']? $_POST['name']:"no-reply");
    $theme = ($_POST['theme']? $_POST['theme']:"ВНИМАНИЕ! С сайта studiowest.ru пришла заявка");
    $email =($_POST['email']? $_POST['email']: "no-reply@studiowest.ru");
    $message="<html><head><title>Письмо с сайта studiowest.ru</title></head><body>";
    if($_POST['phone']) $message.="Телефон: ".$_POST['phone'].'<br>';
    if($_POST['time']) $message.="Дата: ".$_POST['time'].'<br>';
    if($_POST['tip']) $message.="Тип: ".$_POST['tip'].'<br>';
    if($_POST['bulk']) $message.="Объём: ".$_POST['bulk'].'<br>';
    if($_POST['dostavka']) $message.="Адрес: ".$_POST['dostavka'].'<br>';
    if($_POST['prise']) $message.="Цена: ".$_POST['prise'].'<br>';
    if($_POST['sale']) $message.="Скидка: ".$_POST['sale'].'<br>';
    if($_POST['contact']) $message.="Контакты: ".$_POST['contact'].'<br>';
    if($_POST['questions']) $message.="Вопросы: ".$_POST['questions'].'<br>';
    if($_POST['message']) $message.="Сообщение: ".$_POST['message'].'<br>';
    $message.="<body></html>";
    
    //$message = $modx->getChunk('myEmailTemplate');
 
    $modx->getService('mail', 'mail.modPHPMailer');
    $modx->mail->set(modMail::MAIL_BODY,$message);
    $modx->mail->set(modMail::MAIL_FROM,$email);
    $modx->mail->set(modMail::MAIL_FROM_NAME,$name);
    $modx->mail->set(modMail::MAIL_SUBJECT,$theme);
    $modx->mail->address('to',$memail);
    $modx->mail->address('reply-to',$email);
    $modx->mail->setHTML(true);
    $modx->mail->attach($path);
    if (!$modx->mail->send()) {
      $modx->log(modX::LOG_LEVEL_ERROR,'An error occurred while trying to send the email: '.$modx->mail->mailer->ErrorInfo);
    }else{
        $res="success";
    }
    $modx->mail->reset();
    break;
}
// Если у нас есть, что отдать на запрос - отдаем и прерываем работу парсера MODX
if (!empty($res)) {
    echo $res;
}else { die('stop');}

function parseResourceField($modx, $resource, string $field): string
{
    $content = (string)$resource->get($field);
    if ($content === '') {
        return '';
    }
    $oldResource = $modx->resource;
    $modx->resource = $resource;
    $parser = $modx->getParser();
    if ($parser) {
        $maxIterations = (int)$modx->getOption('parser_max_iterations', null, 10);
        // Кэшируемые теги [[...]]
        $parser->processElementTags('', $content, false, false, '[[', ']]', [], $maxIterations);
        // Некэшируемые [[!...]] — сюда попадает NpmReadmeSection
        $parser->processElementTags('', $content, true, false, '[[', ']]', [], $maxIterations);
    }
    $modx->resource = $oldResource;
    return $content;
}

function otvet($catalog, $ress)
{
    global $modx;
    $url = $catalog . '/' . $ress->get('alias');
    return json_encode([
        'URL'   => $url,
        'ANONS' => parseResourceField($modx, $ress, 'introtext'),
        'CONT'  => parseResourceField($modx, $ress, 'content'),
        'FON'   => $ress->getTVValue('fonSite'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
function cmp($a, $b) {
    $a=$a->menuindex;
    $b=$b->menuindex;
    if ($a == $b) { return 0;}
    return ($a < $b) ? -1 : 1;
}