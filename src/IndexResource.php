<?php
namespace happybt\coop;

use watoki\curir\Container;
use watoki\curir\delivery\WebRequest;
use watoki\curir\rendering\locating\NoneTemplateLocator;
use watoki\curir\responder\FormatResponder;
use watoki\deli\Request;
use watoki\factory\Factory;

class IndexResource extends Container {

    private $dataDir;

    /**
     * @param Factory $factory <-
     */
    public function __construct(Factory $factory) {
        parent::__construct($factory);
        $this->dataDir = __DIR__ . '/../user/data/';
    }

    /**
     * @param Request|WebRequest $request
     * @return \watoki\curir\delivery\WebResponse
     */
    public function respond(Request $request) {

        $pageFile = $this->dataDir . 'pages/' . $request->getTarget() . '.json';
        if (file_exists($pageFile)) {
            $page = json_decode(file_get_contents($pageFile), true);

            $renderer = $this->createDefaultRenderer();
            $locator = new NoneTemplateLocator(file_get_contents(__DIR__ . '/page.html'));
            return (new FormatResponder($this->assemblePageModel($page), $locator, $renderer))->createResponse($request);
        }

        return parent::respond($request);
    }

    public function doGet() {
        return $this->assembleSiteModel(true);
    }

    private function assemblePageModel($page) {
        return array_merge($this->assembleSiteModel(), [
            'image' => $page['image'],
            'heading' => $page['title'],
            'subheading' => $page['subtitle'],
            'sections' => $page['sections']
        ]);
    }

    private function assembleSiteModel($withPages = false) {
        $site = json_decode(file_get_contents($this->dataDir . '../site.json'), true);

        $model = [
            'title' => $site['title'],
            'brand' => $site['brand'],
            'menu' => $this->assembleMenu(),
            'copyright' => $site['copyright']
        ];
        if ($withPages) {
            $pages = json_decode(file_get_contents($this->dataDir . 'pages.json'), true);
            $model['pages'] = $this->assemblePages($pages);
        }
        return $model;
    }

    private function assemblePages($urls) {
        $pages = [];

        foreach ($urls as $url) {
            $page = json_decode(file_get_contents($this->dataDir . "pages/$url.json"), true);

            $pages[] = [
                'url' => $url,
                'title' => $page['title'],
                'subtitle' => $page['subtitle'],
                'image' => $page['image'],
                'summary' => $page['summary']
            ];
        }
        return $pages;
    }

    private function assembleMenu() {
        $items = [];

        $pages = json_decode(file_get_contents($this->dataDir . 'pages.json'), true);
        foreach ($pages as $url) {
            $page = json_decode(file_get_contents($this->dataDir . "pages/$url.json"), true);

            $sections = [];

            foreach ($page['sections'] as $section) {
                $sections[] = [
                    'anchor' => $section['anchor'],
                    'caption' => $section['menu']
                ];
            }

            if ($sections) {
                $sections[0]['anchor'] = '';
            }

            $items[] = [
                'url' => $url,
                'caption' => $page['menu'],
                'sections' => $sections
            ];
        }
        return $items;
    }
}