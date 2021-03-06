<?php
/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2015 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Embed\Media;

use Alchemy\Phrasea\Application;
use Alchemy\Phrasea\Controller\AbstractDelivery;
use Symfony\Component\HttpFoundation\Request;

class Media extends AbstractDelivery
{
    public function createMediaInformationFromResourceAndRoute(\media_subdef $subdef, $route, array $parameters = [])
    {
        $urlGenerator = $this->app['url_generator'];

        $url = $urlGenerator->generate($route, $parameters, $urlGenerator::ABSOLUTE_URL);

        return new MediaInformation($subdef, Request::create($url), $route, $parameters);
    }

    /**
     * Return all available metaData
     * @param MediaInformation $information
     * @return array
     */
    public function getMetaData(MediaInformation $information)
    {
        $subdef = $information->getResource();
        $record = $subdef->get_record();
        $thumbnail = $record->get_thumbnail();
        $request = $information->getResourceRequest();
        $baseUrl = $request->getSchemeAndHttpHost() . $request->getBasePath();

        $ogMetaData = [];
        $embedMedia = [];
        $oembedMetaData = [];

        $substitutionPath = sprintf(
            '/assets/common/images/icons/substitution/%s.png',
            str_replace('/', '_', $record->getMimeType())
        );

        $resourceUrl = $information->getUrl();

        $embedMedia['title'] = $record->get_title();
        $embedMedia['url'] = $resourceUrl;

        switch ($record->getType()) {
            case 'video':
                $ogMetaData['og:type'] = 'video.other';
                $ogMetaData['og:image'] = $baseUrl . $thumbnail->get_url();
                $ogMetaData['og:image:width'] = $thumbnail->get_width();
                $ogMetaData['og:image:height'] = $thumbnail->get_height();

                $embedMedia['coverUrl'] = $baseUrl . $thumbnail->get_url();
                $embedMedia['source'] = [];
                $embedMedia['source'][] = [
                    'url'  => $resourceUrl,
                    'type' => $subdef->get_mime(),
                ];
                $embedMedia['dimensions'] = $this->getDimensions($subdef);

                $oembedMetaData['type'] = 'video';
                $oembedMetaData['html'] = sprintf(
                    '<iframe width="%d" height="%d" src="%s" frameborder="0" allowfullscreen></iframe>',
                    $embedMedia['dimensions']['width'],
                    $embedMedia['dimensions']['height'],
                    $this->getEmbedUrl($resourceUrl)
                );
                break;
            case 'flexpaper':
            case 'document':
                $ogMetaData['og:type'] = 'article';
                $ogMetaData['og:image'] = $baseUrl . $thumbnail->get_url();
                $ogMetaData['og:image:width'] = $thumbnail->get_width();
                $ogMetaData['og:image:height'] = $thumbnail->get_height();

                $oembedMetaData['type'] = 'link';
                $embedMedia['dimensions'] = $this->getDimensions($subdef);
                break;
            case 'audio':
                $ogMetaData['og:type'] = 'music.song';
                $ogMetaData['og:image'] = $baseUrl . $substitutionPath;
                $ogMetaData['og:image:width'] = $thumbnail->get_width();
                $ogMetaData['og:image:height'] = $thumbnail->get_height();

                $oembedMetaData['type'] = 'link';
                $embedMedia['source'] = [];
                $embedMedia['source'][] = [
                    'url'  => $resourceUrl,
                    'type' => $subdef->get_mime()
                ];
                $embedMedia['coverUrl'] = $baseUrl . $substitutionPath;
                // set default dimension for player
                $embedMedia['dimensions'] = [
                    'width'  => 320,
                    'height' => 320,
                    'top'    => 0,
                ];
                break;
            default:
                $oembedMetaData['type'] = 'photo';
                $ogMetaData['og:type'] = 'image';
                $ogMetaData['og:image'] = (string)$record->get_preview()->get_permalink()->get_url();
                $ogMetaData['og:image:width'] = $subdef->get_width();
                $ogMetaData['og:image:height'] = $subdef->get_height();

                $embedMedia['dimensions'] = $this->getDimensions($subdef);
                break;
        }

        return [
            'options'        => [
                'autoplay' => false,
            ],
            'oembedMetaData' => $oembedMetaData,
            'ogMetaData'     => $ogMetaData,
            'embedMedia'     => $embedMedia,
        ];
    }

    /**
     * Php raw implementation of thumbnails.html.twig macro
     * @param \media_subdef $subdef
     * @return array
     */
    private function getDimensions(\media_subdef $subdef)
    {
        $outWidth = $subdef->get_width();
        $outHeight = $subdef->get_height() | $outWidth;

        $thumbnail_height = $subdef->get_height() > 0 ? $subdef->get_height() : 120;
        $thumbnail_width = $subdef->get_width() > 0 ? $subdef->get_width() : 120;

        $subdefRatio = 0;
        $thumbnailRatio = $thumbnail_width / $thumbnail_height;
        if ($outWidth > 0 && $outHeight > 0) {
            $subdefRatio = $outWidth / $outHeight;
        }

        if ($thumbnailRatio > $subdefRatio) {
            if ($outWidth > $thumbnail_width) {
                $outWidth = $thumbnail_width;
            }
            $outHeight = $outWidth / $thumbnail_width * $thumbnail_height;
            $top = ($outHeight - $outHeight) / 2;
        } else {
            if ($outHeight > $thumbnail_height) {
                $outHeight = $thumbnail_height;
            }
            $outWidth = $outHeight * $thumbnail_width / $thumbnail_height;
            $top = (($outHeight - $outHeight) / 2);
        }

        return [
            'width'  => round($outWidth),
            'height' => round($outHeight),
            'top'    => $top
        ];
    }

    private function getEmbedUrl($url)
    {
        $urlGenerator = $this->app['url_generator'];

        return $urlGenerator->generate('alchemy_embed_view', ['url' => $url]);
    }
}
