<?php
namespace happybt\coop\admin;

use rtens\domin\delivery\web\Element;
use rtens\domin\delivery\web\fields\AdapterField;
use rtens\domin\delivery\web\fields\EnumerationField;
use rtens\domin\delivery\web\menu\ActionMenuItem;
use rtens\domin\delivery\web\menu\MenuGroup;
use rtens\domin\delivery\web\renderers\link\types\GenericLink;
use rtens\domin\delivery\web\renderers\tables\types\ArrayTable;
use rtens\domin\delivery\web\adapters\curir\root\IndexResource as Domin;
use rtens\domin\delivery\web\WebApplication;
use rtens\domin\Parameter;
use rtens\domin\parameters\Html;
use rtens\domin\parameters\Image;
use rtens\domin\reflection\GenericAction;
use rtens\domin\reflection\GenericMethodAction;
use rtens\domin\reflection\types\EnumerationType;
use watoki\curir\cookie\CookieStore;
use watoki\curir\delivery\FileTarget;
use watoki\curir\delivery\WebRequest;
use watoki\curir\responder\Redirecter;
use watoki\deli\Request;
use watoki\factory\Factory;
use watoki\reflect\type\StringType;
use watoki\stores\file\raw\RawFileStore;

class IndexResource extends Domin {

    private $dataDir;

    /**
     * @param Factory $factory <-
     * @param WebApplication $app <-
     * @param CookieStore $cookies <-
     */
    public function __construct(Factory $factory, WebApplication $app, CookieStore $cookies) {
        parent::__construct($factory, $app, $cookies);

        $this->dataDir = __DIR__ . '/../../user/data/';
        $site = json_decode(file_get_contents($this->dataDir . '../site.json'), true);

        $app->setNameAndBrand($site['title']);

        $this->addAction($app, 'List Pages')
            ->setModifying(false);
        $app->menu->add(new ActionMenuItem('Pages', 'listPages'));

        $this->addAction($app, 'Add Page');
        $app->menu->add(new ActionMenuItem('New', 'addPage'));

        $this->addPageAction($app, "Change Summary")
            ->setFill(function ($params) {
                if (isset($params['url'])) {
                    $params['summary'] = new Html($this->readPage($params['url'])['summary']);
                }
                return $params;
            });

        $this->addPageAction($app, 'Rename Page')
            ->setFill(function ($params) {
                if (isset($params['url'])) {
                    $page = $this->readPage($params['url']);
                    $params['menu'] = $page['menu'];
                    $params['title'] = $page['title'];
                    $params['subtitle'] = $page['subtitle'];
                }
                return $params;
            });

        $this->addPageAction($app, 'List Sections')
            ->setModifying(false);

        $this->addPageAction($app, 'Add Section');

        $this->addPageAction($app, 'Change Page Url');

        $this->addPageAction($app, 'Change Page Position')
            ->setFill(function ($params) {
                if (isset($params['url'])) {
                    $pages = $this->readPages();
                    $params['newPosition'] = array_search($params['url'], $pages) + 1;
                }
                return $params;
            });

        $this->addPageAction($app, 'Change Page Image')
            ->setFill(function ($params) {
                if (isset($params['url'])) {
                    $page = $this->readPage($params['url']);
                    $params['image'] = $page['image'];
                }
                return $params;
            });

        $this->addPageAction($app, 'Delete Page');

        $this->addSectionAction($app, 'Change Content')
            ->setFill(function ($params) {
                if (isset($params['page']) && isset($params['anchor'])) {
                    $section = $this->readSection($params['page'], $params['anchor']);
                    $params['content'] = new Html($section['content']);
                }
                return $params;
            });

        $this->addSectionAction($app, 'Rename Section')
            ->setFill(function ($params) {
                if (isset($params['page']) && isset($params['anchor'])) {
                    $section = $this->readSection($params['page'], $params['anchor']);
                    $params['menu'] = $section['menu'];
                    $params['title'] = $section['title'];
                }
                return $params;
            });

        $this->addSectionAction($app, 'Change Section Position')
            ->setFill(function ($params) {
                if ($params['page'] && $params['anchor']) {
                    $page = $this->readPage($params['page']);
                    foreach ($page['sections'] as $pos => $section) {
                        if ($section['anchor'] == $params['anchor']) {
                            $params['newPosition'] = $pos + 1;
                            break;
                        }
                    }
                }
                return $params;
            });

        $this->addSectionAction($app, 'Move Section To Other Page');

        $this->addSectionAction($app, 'Delete Section');

        $this->addAction($app, 'List Images')
            ->setModifying(false);
        $this->addAction($app, 'Upload Image');
        $app->menu->add((new MenuGroup('Images'))
            ->add(new ActionMenuItem('List', 'listImages'))
            ->add(new ActionMenuItem('Upload', 'uploadImage'))
        );

        $this->addImageAction($app, 'Delete Image');

        $app->fields->add((new AdapterField(new EnumerationField($app->fields)))
            ->setHandlesParameterName('image')
            ->setTransformParameter(function () {
                $baseFolder = __DIR__ . '/../img/';
                $options = [];
                foreach ($this->collectPaths($baseFolder, $baseFolder) as $path) {
                    $options[$path] = $path;
                }
                return new Parameter('image', new EnumerationType($options, new StringType()));
            }));

        $app->defaultAction = 'listPages';
    }

    /**
     * @param Request|WebRequest $request
     * @return \watoki\curir\delivery\WebResponse
     */
    public function respond(Request $request) {
        session_start();
        if (!isset($_SESSION['loggedin'])) {
            return Redirecter::fromString('../login.html')->createResponse($request);
        }

        if (!$request->getTarget()->isEmpty() && $request->getTarget()->getElements()[0] == 'img') {
            $store = new RawFileStore(__DIR__ . '/../img');
            $id = implode('/', array_slice($request->getTarget()->getElements(), 1)) . '.' . $request->getFormats()[0];
            return (new FileTarget($request, $store->read($id), $id))->respond();
        }
        return parent::respond($request);
    }

    /**
     * @param WebApplication $app
     * @param string $caption
     * @return GenericAction
     */
    private function addAction(WebApplication $app, $caption) {
        $id = lcfirst(str_replace(' ', '', $caption));
        return $app->actions->add($id, (new GenericMethodAction($this, $id, $app->types, $app->parser))
            ->generic()->setCaption($caption));
    }

    private function addPageAction(WebApplication $app, $caption) {
        $generic = $this->addAction($app, $caption);

        $id = lcfirst(str_replace(' ', '', $caption));
        $app->links->add(new GenericLink($id, function ($value) {
            return isset($value['url']);
        }, function ($value) {
            return ['url' => $value['url']];
        }));

        return $generic;
    }

    private function addSectionAction(WebApplication $app, $caption) {
        $generic = $this->addAction($app, $caption);

        $id = lcfirst(str_replace(' ', '', $caption));
        $app->links->add(new GenericLink($id, function ($value) {
            return isset($value['page']) && isset($value['anchor']);
        }, function ($value) {
            return [
                'page' => $value['page'],
                'anchor' => $value['anchor']
            ];
        }));

        return $generic;
    }

    private function addImageAction(WebApplication $app, $caption) {
        $generic = $this->addAction($app, $caption);

        $id = lcfirst(str_replace(' ', '', $caption));
        $app->links->add(new GenericLink($id, function ($value) {
            return isset($value['path']) && isset($value['image']);
        }, function ($value) {
            return [
                'path' => $value['path']
            ];
        }));

        return $generic;
    }

    private function readPage($url) {
        return json_decode(file_get_contents($this->dataDir . "pages/$url.json"), true);
    }

    private function updatePage($url, $page) {
        $this->backupPage($url);
        $this->writePage($url, $page);
    }

    private function backupPage($url) {
        $oldPage = $this->readPage($url);
        $date = date('YmdHmi');
        file_put_contents($this->dataDir . "backups/pages/$url.$date.json", json_encode($oldPage, JSON_PRETTY_PRINT));
    }

    private function backupPages() {
        $pages = $this->readPages();
        $date = date('YmdHmi');
        file_put_contents($this->dataDir . "backups/pages.$date.json", json_encode($pages, JSON_PRETTY_PRINT));
    }

    private function createPage($url, $page) {
        if (file_exists($this->dataDir . "pages/$url.json")) {
            throw new \Exception("Page $url already exists");
        }
        $this->writePage($url, $page);

        $pages = $this->readPages();
        $pages[] = $url;
        $this->updatePages($pages);
    }

    private function writePage($url, $page) {
        file_put_contents($this->dataDir . "pages/$url.json", json_encode($page, JSON_PRETTY_PRINT));
    }

    private function readPages() {
        return json_decode(file_get_contents($this->dataDir . 'pages.json'), true);
    }

    private function updatePages($pages) {
        $this->backupPages();
        file_put_contents($this->dataDir . 'pages.json', json_encode($pages, JSON_PRETTY_PRINT));
    }

    private function readSection($page, $anchor) {
        $page = $this->readPage($page);
        foreach ($page['sections'] as $section) {
            if ($section['anchor'] == $anchor) {
                return $section;
            }
        }

        throw new \Exception("Section $anchor does not exist in $page");
    }

    /**
     * @return ArrayTable
     */
    public function listPages() {
        $list = [];

        $pages = $this->readPages();
        foreach ($pages as $url) {
            $page = $this->readPage($url);
            $list[] = [
                'url' => $url,
                'menu' => $page['menu'],
                'title' => $page['title'],
                'subtitle' => $page['subtitle'],
                'image' => $page['image']
            ];
        }

        return new ArrayTable($list);
    }

    /**
     * @param string $url
     * @param Html $summary
     */
    public function changeSummary($url, Html $summary) {
        $page = $this->readPage($url);
        $page['summary'] = $summary->getContent();
        $this->updatePage($url, $page);
    }

    /**
     * @param string $url
     * @param string $menu
     * @param string $title
     * @param string|null $subtitle
     */
    public function renamePage($url, $menu, $title, $subtitle) {
        $page = $this->readPage($url);
        $page['menu'] = $menu;
        $page['title'] = $title;
        $page['subtitle'] = $subtitle;
        $this->updatePage($url, $page);
    }

    /**
     * @param string $url
     * @return ArrayTable
     */
    public function listSections($url) {
        return (new ArrayTable(array_map(function ($row) use ($url) {
            return [
                'page' => $url,
                'anchor' => $row['anchor'],
                'menu' => $row['menu'],
                'title' => $row['title']
            ];
        }, $this->readPage($url)['sections'])));
    }

    /**
     * @param string $page
     * @param string $anchor
     * @param Html $content
     */
    public function changeContent($page, $anchor, Html $content) {
        $pageData = $this->readPage($page);
        foreach ($pageData['sections'] as &$section) {
            if ($section['anchor'] == $anchor) {
                $section['content'] = $content->getContent();

                $this->updatePage($page, $pageData);
                return;
            }
        }
    }

    /**
     * @param string $page
     * @param string $anchor
     * @param string $menu
     * @param string $title
     */
    public function renameSection($page, $anchor, $menu, $title) {
        $pageData = $this->readPage($page);
        foreach ($pageData['sections'] as &$section) {
            if ($section['anchor'] == $anchor) {
                $section['menu'] = $menu;
                $section['title'] = $title;

                $this->updatePage($page, $pageData);
                return;
            }
        }
    }

    /**
     * @param string $url
     * @param string $newUrl
     */
    public function changePageUrl($url, $newUrl) {
        $page = $this->readPage($url);
        $this->createPage($newUrl, $page);
        $this->deletePage($url);
    }

    /**
     * @param string $url
     * @param int $newPosition
     */
    public function changePagePosition($url, $newPosition) {
        $pages = $this->readPages();
        $pos = array_search($url, $pages);
        array_splice($pages, $pos, 1);
        array_splice($pages, $newPosition - 1, 0, $url);
        $this->updatePages($pages);
    }

    /**
     * @param string $url
     */
    public function deletePage($url) {
        $this->backupPage($url);
        unlink($this->dataDir . "pages/$url.json");

        $pages = $this->readPages();
        $pages = array_values(array_diff($pages, [$url]));
        $this->updatePages($pages);
    }

    /**
     * @param string $url
     * @param string $menu The caption of the menu entry.
     * @param string $image Pick the "image" column from "List Images" action.
     * @param string $title Title displayed in the summary and on top of the page.
     * @param null|string $subtitle Displayed underneath the title in the summary and on top of the page.
     * @param Html|null $summary
     */
    public function addPage($url, $menu, $image, $title, $subtitle = null, Html $summary = null) {
        $this->createPage($url, [
            'menu' => $menu,
            'image' => $image,
            'title' => $title,
            'subtitle' => $subtitle,
            'summary' => $summary ? $summary->getContent() : ''
        ]);
    }

    /**
     * @param string $url
     * @param string $anchor ID of the section. Use lower-case without spaces.
     * @param string $menu The caption of the menu entry.
     * @param string $title The title displayed on the page.
     * @param Html $content
     */
    public function addSection($url, $anchor, $menu, $title, Html $content) {
        $page = $this->readPage($url);
        $page['sections'][] = [
            'anchor' => $anchor,
            'menu' => $menu,
            'title' => $title,
            'content' => $content->getContent()
        ];
        $this->updatePage($url, $page);
    }

    /**
     * @param string $page
     * @param string $anchor
     * @param int $newPosition
     * @throws \Exception
     */
    public function changeSectionPosition($page, $anchor, $newPosition) {
        $pageData = $this->readPage($page);

        foreach ($pageData['sections'] as $pos => $section) {
            if ($section['anchor'] == $anchor) {
                break;
            }
        }

        if (!isset($section)) {
            throw new \Exception("Section $anchor does not exist in $page");
        }

        array_splice($pageData['sections'], $pos, 1);
        array_splice($pageData['sections'], $newPosition - 1, 0, [$section]);
        $this->updatePage($page, $pageData);
    }

    /**
     * @param string $page
     * @param string $anchor
     * @return array
     * @throws \Exception
     */
    public function deleteSection($page, $anchor) {
        $data = $this->readPage($page);

        foreach ($data['sections'] as $i => $section) {
            if ($section['anchor'] == $anchor) {
                unset($data['sections'][$i]);
                $data['sections'] = array_values($data['sections']);
                $this->updatePage($page, $data);
                return $section;
            }
        }

        throw new \Exception("Section $anchor does not exist is $page");
    }

    /**
     * @param string $page
     * @param string $anchor
     * @param string $newPage
     */
    public function MoveSectionToOtherPage($page, $anchor, $newPage) {
        $target = $this->readPage($newPage);
        $target['sections'][] = $this->deleteSection($page, $anchor);
        $this->updatePage($newPage, $target);
    }

    /**
     * @param string $url
     * @param string $image
     */
    public function changePageImage($url, $image) {
        $page = $this->readPage($url);
        $page['image'] = $image;
        $this->updatePage($url, $page);
    }

    /**
     * @return ArrayTable
     */
    public function listImages() {
        $baseFolder = __DIR__ . '/../img/';
        $paths = $this->collectPaths($baseFolder, $baseFolder);

        return new ArrayTable(array_map(function ($path) use ($baseFolder) {
            $url = '/img/' . $path;
            return [
                'path' => $path,
                'image' => $url,
                'preview' => new Element('a', ['href' => $url, 'target' => '_blank'], [
                    new Element('img', ['src' => $url, 'width' => '200'])
                ])
            ];
        }, $paths));
    }

    private function collectPaths($baseFolder, $folder) {
        $paths = [];
        foreach (glob($folder . '*') as $file) {
            if (is_dir($file)) {
                $paths = array_merge($paths, $this->collectPaths($baseFolder, $file . '/'));
            } else {
                $paths[] = substr($file, strlen($baseFolder));
            }
        }
        return $paths;
    }

    /**
     * @param string $folder
     * @param Image $newImage
     */
    public function uploadImage($folder, Image $newImage) {
        $baseFolder = __DIR__ . '/../img/';
        $full = $baseFolder . trim($folder) . '/' . $newImage->getFile()->getName();
        if (!file_exists(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }
        $newImage->getFile()->save($full);
    }

    /**
     * @param string $path
     */
    public function deleteImage($path) {
        unlink(__DIR__ . '/../img/' . $path);
    }
}