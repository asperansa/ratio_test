<?
$_SERVER[ 'DOCUMENT_ROOT' ] = "/var/www/zakazkb/data/www/zakazkb-test.xpager.ru";
require( $_SERVER[ 'DOCUMENT_ROOT' ] . "/bitrix/modules/main/include/prolog_before.php" );

\Bitrix\Main\Loader::includeModule('main');
\Bitrix\Main\Loader::includeModule('sale');

class SenderDefferedItems {

    public $message;

    /*
     * Осуществляет рассылку по пользователям, у которых есть брошенные товары в корзине
     **/
    public function Send($days) {

        $dateFrom = new \Bitrix\Main\Type\DateTime;
        $dateTo = new \Bitrix\Main\Type\DateTime;

        $dateFrom->setTime(0, 0, 0)->add('-' . $days.' days');
        $dateTo->setTime(0, 0, 0);

        // cписок пользователей, у которых брошенные товары в корзине за последние 30 дней
        $userListDB = \Bitrix\Sale\Basket::getList(array(
            'filter' => array(
                'DELAY' => 'Y',
                '>DATE_UPDATE' => $dateFrom,
                'ORDER_ID' => ''
            ),
            'order' => array(
                'FUSER.USER.ID' => 'ASC'
            ),
            'group' => array(
                'USER_ID', 'EMAIL', 'NAME', 'USER_PRODUCT_ID'
            ),
            'select' => array(
                'USER_ID' 	=> 'FUSER.USER.ID',
                'EMAIL' 	=> 'FUSER.USER.EMAIL',
                'USERNAME' 		=> 'FUSER.USER.NAME',
                'USER_PRODUCT_ID' => 'PRODUCT.ID'
            )
        ));

        if($userListDB->getSelectedRowsCount() > 0)	{

            while ($userList = $userListDB->fetch()) {
                $defferedProductsList[$userList['EMAIL']][] = $userList['USER_PRODUCT_ID']; # список отложенных товаров по юзерам
            }

            foreach ($defferedProductsList as $email => $products) {

                // Отсылаем по списку пользователей
                \Bitrix\Main\Mail\Event::send(
                    "EVENT_NAME" => "DEFFERED_ITEMS",
					"LID" => "s1",
					"C_FIELDS" => array(
                        "EMAIL" => $email,
                        "PRODUCT_LIST" => $products
                    ),
				);

			}
            $this->message = 'Рассылка успешно произведена.';
            return true;

        } else {
            $this->message = 'Рассылка не была запущена.';
            return false;
        }
    }
} // class SenderDefferedItems..

// @desc Запускаем рассылку
$obSenderDefferedItems = new SenderDefferedItems;

// @desc Если рассылка успешно произведена..
if ($obSenderDefferedItems->Send(30)) {
    $log = $obSenderDefferedItems->message;
} else {
    $log = $obSenderDefferedItems->message;
}

echo $log; # вывод в лог