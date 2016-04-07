<?
$_SERVER[ 'DOCUMENT_ROOT' ] = "/var/www/test_ratio/data/www/ratio-test.asperans.ru";
require( $_SERVER[ 'DOCUMENT_ROOT' ] . "/bitrix/modules/main/include/prolog_before.php" );

\Bitrix\Main\Loader::includeModule('main');
\Bitrix\Main\Loader::includeModule('sale');

class SenderDefferedItems {

    /** @var string */
    public $message;

    /**
     * Осуществляет рассылку по пользователям, у которых есть отложенные товары в корзине за последние N дней
     *
     * @param $days - количество дней, за которые смотрим отложенные товары
     * @return bool
     **/
    public function Send($days) {

        $dateFrom   = new \Bitrix\Main\Type\DateTime;
        $dateTo     = new \Bitrix\Main\Type\DateTime;

        $dateFrom->setTime(0, 0, 0)->add('-' . $days.' days');
        $dateTo->setTime(0, 0, 0);

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
                $defferedProductsList[$userList['USER_ID']]['user_info'] = array(
                    'email' => $userList['EMAIL'],
                    'name' => $userList['USERNAME']
                );

                $defferedProductsList[$userList['USER_ID']]['product_ids'][] = $userList['USER_PRODUCT_ID']; # список отложенных товаров по пользователям
                $defferedProductsList[$userList['USER_ID']]['product_names'][] = $userList['PRODUCT_NAME'];
            }

            foreach ($defferedProductsList as $userID => $productsAndUserInfo) {

                // Проверяем, что отложенные товары не были заказаны пользователем
                $productListDB = \Bitrix\Sale\Basket::getList(array(
                    'filter' => array(
                        'DELAY' => 'N',
                        'USER_PRODUCT_ID' => $productsAndUserInfo['product_ids'],
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
                foreach($productsAndUserInfo['product_ids'] as $key => $product) {
                    if (!in_array($product, $notProductList))
                    $productListForMail .= $productsAndUserInfo['product_names'][$key].'<br/>';
                }

                if (strlen($productListForMail) > 0) {

                    // Отсылаем письма каждому пользователю
                    \Bitrix\Main\Mail\Event::send(array(
                        "EVENT_NAME" => "DEFFERED_ITEMS",
                        "LID"       => "s1",
                        "C_FIELDS"  => array( # поля для подстановки в почтовый шаблон
                            "EMAIL"         => $productsAndUserInfo['user_info']['email'],
                            "USERNAME"      => $productsAndUserInfo['user_info']['name'],
                            "PRODUCT_LIST"  => 'В вашем вишлисте хранятся товары: <br/>' . $productListForMail
                        )
                    ));
                } else {
                    $this->message = 'Рассылка не была запущена.';
                    return false;
                }

			}

            $this->message = 'Рассылка успешно произведена.';
            return true;

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