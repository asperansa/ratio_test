<?
$_SERVER[ 'DOCUMENT_ROOT' ] = "/var/www/test_ratio/data/www/ratio-test.asperans.ru";
require( $_SERVER[ 'DOCUMENT_ROOT' ] . "/bitrix/modules/main/include/prolog_before.php" );

\Bitrix\Main\Loader::includeModule('main');
\Bitrix\Main\Loader::includeModule('sale');

class SenderDefferedItems {

    /** @var string */
    private $eventName = 'DEFFERED_ITEMS';

    /** @var string */
    public $message;

    /**
     * Отправляет Вишлист на e-mail пользователю по шаблону DEFFERED_ITEMS
     *
     * @param $email
     * @param $name
     * @param $wishList
     * @return bool
     **/
    private function SendToEmail($email, $name, $wishList) {
        if (strlen($wishList) > 0) {
            // Отсылаем письма каждому пользователю
            \Bitrix\Main\Mail\Event::send(array(
                "EVENT_NAME" => $this->eventName,
                "LID"       => "s1",
                "C_FIELDS"  => array( # поля для подстановки в почтовый шаблон
                    "EMAIL"         => $email,
                    "USERNAME"      => $name,
                    "PRODUCT_LIST"  => 'В вашем вишлисте хранятся товары: <br/>' . $wishList
                )
            ));
            return true;
        } else {
            return false;
        }
    } //  private function SendToEmail($email, $name, $wishList)..

    /**
     * Осуществляет рассылку по пользователям, у которых есть отложенные товары в корзине за последние N дней
     *
     * @param $days - количество дней, за которые смотрим отложенные товары
     * @return bool
     **/
    public function Send($days) {

        $dateFrom   = new \Bitrix\Main\Type\DateTime;

        $dateFrom->setTime(0, 0, 0)->add('-' . $days.' days');

        // cписок пользователей, у которых брошенные товары в корзине за последние 30 дней
        $userListDB = \Bitrix\Sale\Basket::getList(array(
            'filter' => array(
                'DELAY'         => 'Y',
                '>DATE_UPDATE'  => $dateFrom,
                'ORDER_ID'      => ''
            ),
            'order' => array(
                'FUSER.USER.ID' => 'ASC'
            ),
            'group' => array(
                'USER_ID', 'EMAIL', 'NAME', 'USER_PRODUCT_ID'
            ),
            'select' => array(
                'USER_ID' 	        => 'FUSER.USER.ID',
                'EMAIL' 	        => 'FUSER.USER.EMAIL',
                'USERNAME' 		    => 'FUSER.USER.NAME',
                'USER_PRODUCT_ID'   => 'PRODUCT.ID',
                'PRODUCT_NAME'      => 'NAME'
            )
        ));

        if ($userListDB->getSelectedRowsCount() > 0) {

            while ($userList = $userListDB->fetch()) {
                $defferedProductsList[$userList['USER_ID']]['USER_INFO'] = array(
                    'EMAIL' => $userList['EMAIL'],
                    'NAME' => $userList['USERNAME']
                );

                $defferedProductsList[$userList['USER_ID']]['PRODUCT_IDS'][] = $userList['USER_PRODUCT_ID']; # список отложенных товаров по пользователям
                $defferedProductsList[$userList['USER_ID']]['PRODUCT_NAMES'][] = $userList['PRODUCT_NAME'];
            }

            foreach ($defferedProductsList as $userID => $productsAndUserInfo) {

                // Проверяем, что отложенные товары не были заказаны пользователем
                $productListDB = \Bitrix\Sale\Basket::getList(array(
                    'filter' => array(
                        'DELAY' => 'N',
                        'USER_PRODUCT_ID' => $productsAndUserInfo['PRODUCT_IDS'],
                        '>DATE_UPDATE' => $dateFrom,
                        'USER_ID' => $userID,
                        '!ORDER_ID' => null
                    ),
                    'order' => array(
                        'FUSER.USER.ID' => 'ASC'
                    ),
                    'group' => array(
                        'USER_PRODUCT_ID'
                    ),
                    'select' => array(
                        'USER_ID' 	=> 'FUSER.USER.ID',
                        'USER_PRODUCT_ID' => 'PRODUCT_ID'
                    )
                ));

                $notProductList = array();
                if($productListDB->getSelectedRowsCount() > 0) {

                    while ($productList = $productListDB->fetch()) {
                        # Список товаров, которые не войдут в рассылку
                        $notProductList[] = $productList['USER_PRODUCT_ID'];
                    }

                }

                # формируем строку из списка товаров, удовлетворяющих условиям выше
                $productListForMail = '';
                foreach($productsAndUserInfo['PRODUCTS_IDS'] as $key => $product) {
                    if (!in_array($product, $notProductList))
                    $productListForMail .= $productsAndUserInfo['PRODUCT_NAMES'][$key].'<br/>';
                }

                if ($this->SendToEmail(
                    $productsAndUserInfo['USER_INFO']['EMAIL'],
                    $productsAndUserInfo['USER_INFO']['NAME'],
                    $productListForMail)
                ) {
                    $this->message = 'Рассылка успешно произведена.';
                    return true;
                } else {
                    $this->message = 'Рассылка не была запущена.';
                    return false;
                }
			}

        } else {
            $this->message = 'Рассылка не была запущена.';
            return false;
        }
    } // public function Send($days)..

} // class SenderDefferedItems..

/**
 * @desc Запускаем рассылку
 **/
$obSenderDefferedItems = new SenderDefferedItems;

/**
 * @desc Если рассылка успешно произведена
 **/
if ($obSenderDefferedItems->Send(30)) {
    $log = $obSenderDefferedItems->message;
} else {
    $log = $obSenderDefferedItems->message;
}

echo $log; # вывод в лог