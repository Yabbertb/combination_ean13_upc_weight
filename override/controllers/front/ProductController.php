<?php

class ProductController extends ProductControllerCore
{
    /*
    * Combination: EAN13, UPC and weight
    */
    protected function assignAttributesGroups()
    {
        $colors = [];
        $groups = [];
        $attributesGroups = $this->product->getAttributesGroups($this->context->language->id);
        if (is_array($attributesGroups) && $attributesGroups) {
            $combinationImages = $this->product->getCombinationImages($this->context->language->id);
            $combinationPricesSet = [];
            $link = Context::getContext()->link;
            $combinations = [];
            foreach ($attributesGroups as $row) {
                $combinationId = (int)$row['id_product_attribute'];
                $attributeGroupId = (int)$row['id_attribute_group'];
                $attributeId = (int)$row['id_attribute'];
                if (isset($row['is_color_group']) && $row['is_color_group'] && (isset($row['attribute_color']) && $row['attribute_color']) || (ImageManager::getSourceImage(_PS_COL_IMG_DIR_, $attributeId))) {
                    $colors[$attributeId]['value'] = $row['attribute_color'];
                    $colors[$attributeId]['name'] = $row['attribute_name'];
                    if (!isset($colors[$attributeId]['attributes_quantity'])) {
                        $colors[$attributeId]['attributes_quantity'] = 0;
                    }
                    $colors[$attributeId]['attributes_quantity'] += (int) $row['quantity'];
                }
                if (!isset($groups[$attributeGroupId])) {
                    $groups[$attributeGroupId] = [
                        'group_name' => $row['group_name'],
                        'name'       => $row['public_group_name'],
                        'group_type' => $row['group_type'],
                        'default'    => -1,
                    ];
                }
                $groups[$attributeGroupId]['attributes'][$attributeId] = $row['attribute_name'];
                if ($row['default_on'] && $groups[$attributeGroupId]['default'] == -1) {
                    $groups[$attributeGroupId]['default'] = (int) $attributeId;
                }
                if (!isset($groups[$attributeGroupId]['attributes_quantity'][$attributeId])) {
                    $groups[$attributeGroupId]['attributes_quantity'][$attributeId] = 0;
                }
                $groups[$attributeGroupId]['attributes_quantity'][$attributeId] += (int) $row['quantity'];
                $combinations[$combinationId]['attributes_values'][$attributeGroupId] = $row['attribute_name'];
                $combinations[$combinationId]['attributes'][] = $attributeId;
                $combinations[$combinationId]['price'] = Tools::convertPriceFull($row['price'], null, $this->context->currency);
                $combinations[$combinationId]['hashUrl'] = $link->getCombinationHashUrl((int)$this->product->id, (int)$row['id_product_attribute']);
                if (!isset($combinationPricesSet[(int) $row['id_product_attribute']])) {
                    Product::getPriceStatic(
                        (int) $this->product->id,
                        false,
                        $row['id_product_attribute'],
                        _TB_PRICE_DATABASE_PRECISION_,
                        null,
                        false,
                        false,
                        1,
                        false,
                        null,
                        null,
                        null,
                        $combinationSpecificPrice
                    );
                    $combinationPricesSet[$combinationId] = true;
                    $combinations[$combinationId]['specific_price'] = $combinationSpecificPrice;
                }
                $combinations[$combinationId]['ecotax'] = (float) $row['ecotax'];
                $combinations[$combinationId]['weight'] = (float) $row['weight'];
                $combinations[$combinationId]['quantity'] = (int) $row['quantity'];
                $combinations[$combinationId]['reference'] = $row['reference'];
                $combinations[$combinationId]['ean13'] = $row['ean13'];
                $combinations[$combinationId]['upc'] = $row['upc'];
                $combinations[$combinationId]['unit_impact'] = Tools::convertPriceFull($row['unit_price_impact'], null, $this->context->currency);
                $combinations[$combinationId]['minimal_quantity'] = $row['minimal_quantity'];
                if ($row['available_date'] != '0000-00-00' && Validate::isDate($row['available_date'])) {
                    $combinations[$combinationId]['available_date'] = $row['available_date'];
                    $combinations[$combinationId]['date_formatted'] = Tools::displayDate($row['available_date']);
                } else {
                    $combinations[$combinationId]['available_date'] = $combinations[$combinationId]['date_formatted'] = '';
                }
                if (!isset($combinationImages[$combinationId][0]['id_image'])) {
                    $combinations[$combinationId]['id_image'] = -1;
                } else {
                    $combinations[$combinationId]['id_image'] = $idImage = (int) $combinationImages[$combinationId][0]['id_image'];
                    if ($row['default_on']) {
                        if (isset($this->context->smarty->tpl_vars['cover']->value)) {
                            $currentCover = $this->context->smarty->tpl_vars['cover']->value;
                        }
                        if (is_array($combinationImages[$combinationId])) {
                            foreach ($combinationImages[$combinationId] as $tmp) {
                                if (isset($currentCover) && $tmp['id_image'] == $currentCover['id_image']) {
                                    $combinations[$combinationId]['id_image'] = $idImage = (int) $tmp['id_image'];
                                    break;
                                }
                            }
                        }
                        if ($idImage > 0) {
                            if (isset($this->context->smarty->tpl_vars['images']->value)) {
                                $productImages = $this->context->smarty->tpl_vars['images']->value;
                            }
                            if (isset($productImages[$idImage]) && is_array($productImages)) {
                                $productImages[$idImage]['cover'] = 1;
                                $this->context->smarty->assign('mainImage', $productImages[$idImage]);
                                if (count($productImages)) {
                                    $this->context->smarty->assign('images', $productImages);
                                }
                            }
                            if (isset($this->context->smarty->tpl_vars['cover']->value)) {
                                $cover = $this->context->smarty->tpl_vars['cover']->value;
                            }
                            if (isset($cover) && is_array($cover) && isset($productImages) && is_array($productImages)) {
                                $productImages[$cover['id_image']]['cover'] = 0;
                                if (isset($productImages[$idImage])) {
                                    $cover = $productImages[$idImage];
                                }
                                $cover['id_image'] = (int) $idImage;
                                $cover['id_image_only'] = (int) $idImage;
                                $this->context->smarty->assign('cover', $cover);
                            }
                        }
                    }
                }
            }
            if (!Product::isAvailableWhenOutOfStock($this->product->out_of_stock) && Configuration::get('PS_DISP_UNAVAILABLE_ATTR') == 0) {
                foreach ($groups as &$group) {
                    foreach ($group['attributes_quantity'] as $key => $quantity) {
                        if ($quantity <= 0) {
                            unset($group['attributes'][$key]);
                        }
                    }
                }
                foreach ($colors as $key => $color) {
                    if ($color['attributes_quantity'] <= 0) {
                        unset($colors[$key]);
                    }
                }
            }
            if (isset($combinations)) {
                foreach ($combinations as $idProductAttribute => $comb) {
                    $attributeList = '';
                    foreach ($comb['attributes'] as $idAttribute) {
                        $attributeList .= '\''.(int) $idAttribute.'\',';
                    }
                    $attributeList = rtrim($attributeList, ',');
                    $combinations[$idProductAttribute]['list'] = $attributeList;
                }
            }
            $this->context->smarty->assign(
                [
                    'groups'            => $groups,
                    'colors'            => (count($colors)) ? $colors : false,
                    'combinations'      => $combinations ?? [],
                    'combinationImages' => $combinationImages,
                ]
            );
        }
    }
}
