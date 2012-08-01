<?php

/* Расширение для экспорта MediaWiki-статей в HTML-файлы.
   Добавьте в данный коктейль SSI и получите самый настоящий сайт.
   (c) Виталий Филиппов <vitalif@yourcmc.ru>, 2009+
   Лицензия: GPLv3 или более поздняя */

if (!defined('MEDIAWIKI')) die("Not an entry point.");

class MediaWikiSiteExport
{
    var $pending = array();
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
    /* получение включений статьи как шаблона или как изображения */
    static function get_links($title)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            array('templatelinks', 'page'), 'page.*',
            array('page_id=tl_from', 'tl_title' => $title->getDBkey(), 'tl_namespace' => $title->getNamespace()),
            __METHOD__);
        $pages = array();
        foreach ($res as $row)
            $pages[$row->page_id] = Title::newFromRow($row);
        if ($title->getNamespace() == NS_FILE)
        {
            // Для файлов добавляем включения файлов
            $res = $dbr->select(
                array('imagelinks', 'page'), 'page.*',
                array('page_id=il_from', 'il_to' => $title->getDBkey()),
                __METHOD__);
            foreach ($res as $row)
                if (!$pages[$row->page_id])
                    $pages[$row->page_id] = Title::newFromRow($row);
        }
        return $pages;
    }
    /**
     * Добавить статью в очередь обновлений
     * Всегда по Title'у, ибо объект статьи всегда пересоздаётся,
     * чтобы не зависеть от его текущего состояния.
     */
    public function enqueue(Title $title)
    {
        $id = $title->getArticleId();
        if ($id && empty($this->pending[$id]))
            $this->pending[$id] = $title;
    }
    /* Вызывается через $wgDeferredUpdateList после конца запроса, обновляет статьи */
    public function doUpdate()
    {
        // Очищаем кэш RepoGroup, чтобы при импорте корректно отразились картинки
        RepoGroup::singleton()->cache = array();
        reset($this->pending);
        // Используем each, чтобы массив можно было дополнять на ходу
        while (list($id, $title) = each($this->pending))
            $this->update_article($title);
        $this->pending = array();
    }
    /* обновление статьи */
    protected function update_article($title, $text = NULL, $revision = NULL)
    {
        global $wgSiteExportHandlers, $wgParser, $wgContLang;
        $res = false;
        $article = class_exists('WikiPage') ? new WikiPage($title) : new Article($title);
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
                    $text = $article instanceof WikiPage ? $article->getText() : $article->getContent();
                if (is_null($revision) && $article->getRevision())
                    $revision = $article->getRevision()->getId();
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
                    $status = call_user_func_array($hspec['callback'], array(&$this, &$html, &$fn, $article, $hspec));
                    if ($status && $fn && self::mkpath(dirname($fn)) && ($fp = fopen($fn, "wb")))
                    {
                        wfDebug("Site export $title $revision --> $fn\n");
                        fwrite($fp, $html);
                        fclose($fp);
                    }
                }
                $res = true;
                break;
            }
        }
        /* Обновляем статьи, включающие эту */
        $includingPages = self::get_links($title);
        foreach ($includingPages as $page)
            $this->enqueue($page);
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
}
