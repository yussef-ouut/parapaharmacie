<?php
/**
 * 2026 Youssef Aotarid
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @author    Youssef Aotarid <youssef.aotarid@bts-dwfs.fr>
 * @copyright 2026 Youssef Aotarid
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ParaChatbotProductService
{
    private $context;

    public function __construct($context = null)
    {
        $this->context = $context ? $context : Context::getContext();
    }

    /**
     * Recherche de produits dynamiquement dans PrestaShop.
     */
    public function searchProducts($query, $limit = 5)
    {
        $words = explode(' ', strtolower(trim($query)));
        $cleanWords = array();
        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-zA-Z0-9\x7f-\xff]/', '', $word);
            if (strlen($cleanWord) > 2) {
                $cleanWords[] = $cleanWord;
            }
        }

        if (empty($cleanWords)) {
            $cleanWords = array($query);
        }

        return $this->searchProductsByKeywords($cleanWords, $limit);
    }

    /**
     * Recherche de produits par mots-clés
     */
    public function searchProductsByKeywords(array $keywords, $limit = 5)
    {
        $db = Db::getInstance();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)$this->context->shop->id;
        
        $where = array();
        foreach ($keywords as $keyword) {
            $where[] = "pl.name LIKE '%" . pSQL($keyword) . "%' OR pl.description_short LIKE '%" . pSQL($keyword) . "%' OR pl.description LIKE '%" . pSQL($keyword) . "%'";
        }
        
        if (empty($where)) {
            return array();
        }
        
        $sql = "SELECT p.id_product, pl.name, pl.description_short, p.price, pl.link_rewrite, cl.name AS category_name
                FROM " . _DB_PREFIX_ . "product p
                JOIN " . _DB_PREFIX_ . "product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = " . $id_shop . ")
                JOIN " . _DB_PREFIX_ . "product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = " . $id_shop . " AND pl.id_lang = " . $id_lang . ")
                LEFT JOIN " . _DB_PREFIX_ . "category_lang cl ON (p.id_category_default = cl.id_category AND cl.id_shop = " . $id_shop . " AND cl.id_lang = " . $id_lang . ")
                WHERE ps.active = 1 AND (" . implode(' OR ', $where) . ")
                GROUP BY p.id_product
                LIMIT " . (int)$limit;
                
        $results = $db->executeS($sql);
        return $this->formatProducts($results);
    }

    /**
     * Récupère tous les produits (avec limite)
     */
    public function getProducts($limit = 10)
    {
        $db = Db::getInstance();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)$this->context->shop->id;

        $sql = "SELECT p.id_product, pl.name, pl.description_short, p.price, pl.link_rewrite, cl.name AS category_name
                FROM " . _DB_PREFIX_ . "product p
                JOIN " . _DB_PREFIX_ . "product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = " . $id_shop . ")
                JOIN " . _DB_PREFIX_ . "product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = " . $id_shop . " AND pl.id_lang = " . $id_lang . ")
                LEFT JOIN " . _DB_PREFIX_ . "category_lang cl ON (p.id_category_default = cl.id_category AND cl.id_shop = " . $id_shop . " AND cl.id_lang = " . $id_lang . ")
                WHERE ps.active = 1
                GROUP BY p.id_product
                ORDER BY p.date_add DESC
                LIMIT " . (int)$limit;

        $results = $db->executeS($sql);
        return $this->formatProducts($results);
    }

    /**
     * Récupère un produit par son ID
     */
    public function getProductById($id_product)
    {
        $db = Db::getInstance();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)$this->context->shop->id;

        $sql = "SELECT p.id_product, pl.name, pl.description_short, p.price, pl.link_rewrite, cl.name AS category_name
                FROM " . _DB_PREFIX_ . "product p
                JOIN " . _DB_PREFIX_ . "product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = " . $id_shop . ")
                JOIN " . _DB_PREFIX_ . "product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = " . $id_shop . " AND pl.id_lang = " . $id_lang . ")
                LEFT JOIN " . _DB_PREFIX_ . "category_lang cl ON (p.id_category_default = cl.id_category AND cl.id_shop = " . $id_shop . " AND cl.id_lang = " . $id_lang . ")
                WHERE p.id_product = " . (int)$id_product . " AND ps.active = 1";

        $results = $db->executeS($sql);
        if ($results && count($results) > 0) {
            $formatted = $this->formatProducts($results);
            return $formatted[0];
        }
        return null;
    }

    /**
     * Récupère les produits d'une catégorie spécifique
     */
    public function getProductsByCategory($id_category, $limit = 5)
    {
        $db = Db::getInstance();
        $id_lang = (int)$this->context->language->id;
        $id_shop = (int)$this->context->shop->id;

        $sql = "SELECT p.id_product, pl.name, pl.description_short, p.price, pl.link_rewrite, cl.name AS category_name
                FROM " . _DB_PREFIX_ . "product p
                JOIN " . _DB_PREFIX_ . "product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = " . $id_shop . ")
                JOIN " . _DB_PREFIX_ . "category_product cp ON (p.id_product = cp.id_product)
                JOIN " . _DB_PREFIX_ . "product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = " . $id_shop . " AND pl.id_lang = " . $id_lang . ")
                LEFT JOIN " . _DB_PREFIX_ . "category_lang cl ON (p.id_category_default = cl.id_category AND cl.id_shop = " . $id_shop . " AND cl.id_lang = " . $id_lang . ")
                WHERE cp.id_category = " . (int)$id_category . " AND ps.active = 1
                GROUP BY p.id_product
                LIMIT " . (int)$limit;

        $results = $db->executeS($sql);
        return $this->formatProducts($results);
    }

    /**
     * Formate les résultats SQL en tableau structuré
     */
    private function formatProducts($results)
    {
        $products = array();
        if ($results) {
            foreach ($results as $row) {
                try {
                    $price_tax_incl = Product::getPriceStatic($row['id_product'], true);
                } catch (\Throwable $e) {
                    $price_tax_incl = (float)$row['price'];
                }

                try {
                    $stock_qty = (int)Product::getQuantity($row['id_product']);
                } catch (\Throwable $e) {
                    $stock_qty = 0;
                }
                
                $currency = $this->context->currency;
                $iso_code = ($currency && isset($currency->iso_code)) ? $currency->iso_code : 'MAD';
                $sign = ($currency && isset($currency->sign)) ? $currency->sign : 'DH';
                try {
                    $formatted_price = Tools::getContextLocale($this->context)->formatPrice($price_tax_incl, $iso_code);
                } catch (\Throwable $e) {
                    $formatted_price = number_format($price_tax_incl, 2, '.', ' ') . ' ' . $sign;
                }
                
                try {
                    $link = $this->getProductLink($row['id_product'], $row['link_rewrite']);
                } catch (\Throwable $e) {
                    $link = '#';
                }

                try {
                    $image = $this->getProductImage($row['id_product'], $row['link_rewrite']);
                } catch (\Throwable $e) {
                    $image = '';
                }

                $products[] = array(
                    "id_product" => (int)$row['id_product'],
                    "name" => $row['name'],
                    "description" => trim(strip_tags($row['description_short'])),
                    "category" => $row['category_name'] ? $row['category_name'] : 'Parapharmacie',
                    "price" => $formatted_price,
                    "stock" => $stock_qty,
                    "link" => $link,
                    "image" => $image
                );
            }
        }
        return $products;
    }

    /**
     * Génère l'URL d'un produit
     */
    public function getProductLink($id_product, $link_rewrite = null)
    {
        if ($link_rewrite === null) {
            $product = new Product($id_product, false, $this->context->language->id);
            $link_rewrite = $product->link_rewrite;
        }
        return $this->context->link->getProductLink($id_product, $link_rewrite);
    }

    /**
     * Génère l'URL de l'image de couverture d'un produit
     */
    public function getProductImage($id_product, $link_rewrite = null)
    {
        if ($link_rewrite === null) {
            $product = new Product($id_product, false, $this->context->language->id);
            $link_rewrite = $product->link_rewrite;
        }
        $id_lang = (int)$this->context->language->id;
        $images = Image::getImages($id_lang, $id_product);
        if (!empty($images)) {
            return $this->context->link->getImageLink($link_rewrite, $id_product . '-' . $images[0]['id_image'], 'home_default');
        }
        return '';
    }
}
