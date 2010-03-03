<?php

/* Расширение для экспорта MediaWiki-статей в HTML-файлы.
   Добавьте в данный коктейль SSI и получите самый настоящий сайт.
   (c) Виталий Филиппов <vitalif@yourcmc.ru>, 2009-2010 */

if (!defined('MEDIAWIKI')) die("Not an entry point.");

class MediaWikiSiteExport
{
    /* отслеживаем уже обновлённые статьи, чтобы повторно не обновлять и не зацикливаться */
    var $track = array();
    /* рекурсивное создание каталога */
    static function mkpath($path, $mode = 0755)
    {
        if (preg_match('#[^/\\\\]+[/\\\\]*$#is', $path, $m))
        {
            if (!self::mkpath(substr($path, 0, strlen($path)-strlen($m[0]))))
                return false;
            if (!file_exists($path))
            {
                mkdir($path);
                chmod($path, $mode);
            }
            return file_exists($path);
        }
        return true;
    }
    /* получение включений статьи */
    static function get_links($title)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            'templatelinks',
            array('tl_from'),
            array('tl_title' => $title->getDBkey(), 'tl_namespace' => $title->getNamespace()),
            __METHOD__);
        $pages = array();
        while ($row = $dbr->fetchRow($res))
            $pages[] = Title::newFromId($row['tl_from']);
        return $pages;
    }
    /* обновление статьи по заголовку */
    function update_title($title)
    {
        if (!is_object($title))
            $title = Title::makeTitleSafe(NS_MAIN, $title);
        if (!$title)
            return false;
        return $this->update_article(new Article($title));
    }
    /* обновление статьи */
    function update_article(&$article, $text = NULL, $revision = NULL)
    {
        global $wgSiteExportHandlers, $wgParser, $wgContLang;
        $res = false;
        $title = $article->getTitle();
        if ($this->track[$title->getPrefixedText()])
            return true;
        $key = $title->getText();
        $dbkey = $title->getDBkey();
        foreach ($wgSiteExportHandlers as $prefix => $hspec)
        {
            if (substr($key, 0, strlen($prefix)) == $prefix)
            {
                /* парсим статью */
                $options = new ParserOptions;
                $options->setTidy(true);
                $options->setEditSection(false);
                $options->setRemoveComments(false);
                if (is_null($text))
                {
                    $article->loadContent();
                    $text = $article->mContent;
                }
                if (is_null($revision) && $article->mRevision)
                    $revision = $article->mRevision->getId();
                $html = $wgParser->parse($text, $title, $options, true, true, $revision)->getText();
                /* имя файла по умолчанию */
                if ($hspec['fspath'])
                {
                    $hspec['fspath'] = preg_replace('#(?<!/)/*$#s', '/', $hspec['fspath']);
                    $ns = $title->getNamespace();
                    $ns = $ns ? $wgContLang->getNsText($ns) . '/' : '';
                    $fn = $hspec['fspath'] . $ns . trim(substr($dbkey, strlen($prefix)), " _\t\r\n\0\x0B") . '.htm';
                }
                else
                    $fn = '';
                /* аргументы для ссылок обратно */
                if (is_callable($hspec['callback']))
                {
                    $this->track[$title->getPrefixedText()] = true;
                    $status = call_user_func_array($hspec['callback'], array(&$this, &$html, &$fn, $article, $hspec));
                    if ($status && $fn && self::mkpath(dirname($fn)) && ($fp = fopen($fn, "wb")))
                    {
                        fwrite($fp, $html);
                        fclose($fp);
                    }
                }
                $res = true;
                break;
            }
        }
        $pages = self::get_links($title);
        foreach ($pages as $page)
            $this->update_title($page);
        return $res;
    }
    /* XSLT-преобразование HTML-кода */
    static $xslt_cache = array();
    static function xslt($html, $stylesheet)
    {
        $stylesheet = dirname(__FILE__) . '/' . str_replace('..', '', $stylesheet);
        if (!($proc = self::$xslt_cache[$stylesheet]))
        {
            $xsl = new DOMDocument();
            $xsl->load($stylesheet);
            $proc = new XSLTProcessor();
            $proc->importStylesheet($xsl);
            self::$xslt_cache[$stylesheet] = $proc;
        }
        $dom = new DOMDocument();
        $text = "<root>$html</root>";
        $dom->loadXML($text);
        return $proc->transformToXML($dom);
    }
    /* Singleton */
    static $singleton;
    static function singleton()
    {
        if (!self::$singleton)
            self::$singleton = new MediaWikiSiteExport();
        return self::$singleton;
    }
}
