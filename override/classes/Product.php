<?php

class Product extends ProductCore
{
    /*
    * Combination: EAN13, UPC and weight
    */
    public function getAttributesGroups($idLang)
    {
        if (!Combination::isFeatureActive()) {
            return [];
        }
        $sql = 'SELECT ag.`id_attribute_group`, ag.`is_color_group`, agl.`name` AS group_name, agl.`public_name` AS public_group_name,
				a.`id_attribute`, al.`name` AS attribute_name, a.`color` AS attribute_color, product_attribute_shop.`id_product_attribute`,
				IFNULL(stock.quantity, 0) AS quantity, product_attribute_shop.`price`, product_attribute_shop.`ecotax`, product_attribute_shop.`weight`,
				product_attribute_shop.`default_on`, pa.`reference`, pa.`ean13`, pa.`upc`, product_attribute_shop.`unit_price_impact`,
				product_attribute_shop.`minimal_quantity`, product_attribute_shop.`available_date`, ag.`group_type`,
				product_attribute_shop.`width`, product_attribute_shop.`height`, product_attribute_shop.`depth`
				FROM `'._DB_PREFIX_.'product_attribute` pa
				'.Shop::addSqlAssociation('product_attribute', 'pa').'
				'.static::sqlStock('pa', 'pa').'
				LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
				LEFT JOIN `'._DB_PREFIX_.'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
				LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
				LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute`)
				LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group`)
				'.Shop::addSqlAssociation('attribute', 'a').'
				WHERE pa.`id_product` = '.(int) $this->id.'
					AND al.`id_lang` = '.(int) $idLang.'
					AND agl.`id_lang` = '.(int) $idLang.'
				GROUP BY id_attribute_group, id_product_attribute
				ORDER BY ag.`position` ASC, a.`position` ASC, agl.`name` ASC';
        return Db::readOnly()->getArray($sql);
    }
    /*
    * Combination: EAN13, UPC and weight
    */
    public static function getAttributesInformationsByProduct($idProduct)
    {
        $conn = Db::readOnly();
        if (Module::isInstalled('blocklayered') && Module::isEnabled('blocklayered')) {
            $nbCustomValues = $conn->getArray('
			SELECT DISTINCT la.`id_attribute`, la.`url_name` AS `attribute`
			FROM `'._DB_PREFIX_.'attribute` a
			LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac
				ON (a.`id_attribute` = pac.`id_attribute`)
			LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
				ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
			'.Shop::addSqlAssociation('product_attribute', 'pa').'
			LEFT JOIN `'._DB_PREFIX_.'layered_indexable_attribute_lang_value` la
				ON (la.`id_attribute` = a.`id_attribute` AND la.`id_lang` = '.(int) Context::getContext()->language->id.')
			WHERE la.`url_name` IS NOT NULL AND la.`url_name` != \'\'
			AND pa.`id_product` = '.(int) $idProduct
            );
            if (!empty($nbCustomValues)) {
                $tabIdAttribute = [];
                foreach ($nbCustomValues as $attribute) {
                    $tabIdAttribute[] = $attribute['id_attribute'];
                    $group = $conn->getArray('
					SELECT g.`id_attribute_group`, g.`url_name` AS `group`
					FROM `'._DB_PREFIX_.'layered_indexable_attribute_group_lang_value` g
					LEFT JOIN `'._DB_PREFIX_.'attribute` a
						ON (a.`id_attribute_group` = g.`id_attribute_group`)
					WHERE a.`id_attribute` = '.(int) $attribute['id_attribute'].'
					AND g.`id_lang` = '.(int) Context::getContext()->language->id.'
					AND g.`url_name` IS NOT NULL AND g.`url_name` != \'\''
                    );
                    if (empty($group)) {
                        $group = $conn->getArray('
						SELECT g.`id_attribute_group`, g.`name` AS `group`
						FROM `'._DB_PREFIX_.'attribute_group_lang` g
						LEFT JOIN `'._DB_PREFIX_.'attribute` a
							ON (a.`id_attribute_group` = g.`id_attribute_group`)
						WHERE a.`id_attribute` = '.(int) $attribute['id_attribute'].'
						AND g.`id_lang` = '.(int) Context::getContext()->language->id.'
						AND g.`name` IS NOT NULL'
                        );
                    }
                    $result[] = array_merge($attribute, $group[0]);
                }
                $valuesNotCustom = $conn->getArray('
				SELECT DISTINCT a.`id_attribute`, a.`id_attribute_group`, al.`name` AS `attribute`, agl.`name` AS `group`, pa.`ean13`, pa.`upc`
				FROM `'._DB_PREFIX_.'attribute` a
				LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al
					ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int) Context::getContext()->language->id.')
				LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
					ON (a.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int) Context::getContext()->language->id.')
				LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac
					ON (a.`id_attribute` = pac.`id_attribute`)
				LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
					ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
				'.Shop::addSqlAssociation('product_attribute', 'pa').'
				'.Shop::addSqlAssociation('attribute', 'pac').'
				WHERE pa.`id_product` = '.(int) $idProduct.'
				AND a.`id_attribute` NOT IN('.implode(', ', $tabIdAttribute).')'
                );
                $result = array_merge($valuesNotCustom, $result);
            } else {
                $result = $conn->getArray('
				SELECT DISTINCT a.`id_attribute`, a.`id_attribute_group`, al.`name` AS `attribute`, agl.`name` AS `group`, pa.`ean13`, pa.`upc`
				FROM `'._DB_PREFIX_.'attribute` a
				LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al
					ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int) Context::getContext()->language->id.')
				LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
					ON (a.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int) Context::getContext()->language->id.')
				LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac
					ON (a.`id_attribute` = pac.`id_attribute`)
				LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
					ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
				'.Shop::addSqlAssociation('product_attribute', 'pa').'
				'.Shop::addSqlAssociation('attribute', 'pac').'
				WHERE pa.`id_product` = '.(int) $idProduct
                );
            }
        } else {
            $result = $conn->getArray('
			SELECT DISTINCT a.`id_attribute`, a.`id_attribute_group`, al.`name` AS `attribute`, agl.`name` AS `group`, pa.`ean13`, pa.`upc`
			FROM `'._DB_PREFIX_.'attribute` a
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al
				ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int) Context::getContext()->language->id.')
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
				ON (a.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int) Context::getContext()->language->id.')
			LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac
				ON (a.`id_attribute` = pac.`id_attribute`)
			LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
				ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
			'.Shop::addSqlAssociation('product_attribute', 'pa').'
			'.Shop::addSqlAssociation('attribute', 'pac').'
			WHERE pa.`id_product` = '.(int) $idProduct
            );
        }
        return $result;
    }
}
