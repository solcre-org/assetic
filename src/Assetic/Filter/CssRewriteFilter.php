<?php

/*
 * This file is part of the Assetic package, an OpenSky project.
 *
 * (c) 2010-2014 OpenSky Project Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Assetic\Filter;

use Assetic\Asset\AssetInterface;

/**
 * Fixes relative CSS urls.
 *
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 */
class CssRewriteFilter extends BaseCssFilter
{
    public function filterLoad(AssetInterface $asset)
    {
    }

    public function filterDump(AssetInterface $asset)
    {
        $sourceBase = $asset->getSourceRoot();
        $sourcePath = $asset->getSourcePath();
        $targetPath = $asset->getTargetPath();

        if (null === $sourcePath || null === $targetPath || $sourcePath == $targetPath) {
            return;
        }

        // learn how to get from the target back to the source
        if (false !== strpos($sourceBase, '://')) {
            list($scheme, $url) = explode('://', $sourceBase . \DIRECTORY_SEPARATOR . $sourcePath, 2);
            list($host, $path) = explode(\DIRECTORY_SEPARATOR, $url, 2);

            $host = $scheme . '://' . $host . \DIRECTORY_SEPARATOR;
            $path = false === strpos($path, \DIRECTORY_SEPARATOR) ? '' : dirname($path);
            $path .= \DIRECTORY_SEPARATOR;
        } else {
            // assume source and target are on the same host
            $host = '';

            // pop entries off the target until it fits in the source
            if ('.' == dirname($sourcePath)) {
                $path = str_repeat('../', substr_count($targetPath, \DIRECTORY_SEPARATOR));
            } elseif ('.' == $targetDir = dirname($targetPath)) {
                $path = dirname($sourcePath) . \DIRECTORY_SEPARATOR;
            } else {
                $path = '';
                while (0 !== strpos($sourcePath, $targetDir)) {
                    if (false !== $pos = strrpos($targetDir, \DIRECTORY_SEPARATOR)) {
                        $targetDir = substr($targetDir, 0, $pos);
                        $path .= '../';
                    } else {
                        $targetDir = '';
                        $path .= '../';
                        break;
                    }
                }
                $path .= ltrim(substr(dirname($sourcePath) . \DIRECTORY_SEPARATOR, strlen($targetDir)), \DIRECTORY_SEPARATOR);
            }
        }

        $content = $this->filterReferences($asset->getContent(), function ($matches) use ($host, $path) {
            if (false !== strpos($matches['url'], '://') || 0 === strpos($matches['url'], '//') || 0 === strpos($matches['url'], 'data:')) {
                // absolute or protocol-relative or data uri
                return $matches[0];
            }

            if (isset($matches['url'][0]) && \DIRECTORY_SEPARATOR == $matches['url'][0]) {
                // root relative
                return str_replace($matches['url'], $host . $matches['url'], $matches[0]);
            }

            // document relative
            $url = $matches['url'];
            while (0 === strpos($url, '../') && 2 <= substr_count($path, \DIRECTORY_SEPARATOR)) {
                $path = substr($path, 0, strrpos(rtrim($path, \DIRECTORY_SEPARATOR), \DIRECTORY_SEPARATOR) + 1);
                $url = substr($url, 3);
            }

            $parts = array();
            foreach (explode(\DIRECTORY_SEPARATOR, $host . $path . $url) as $part) {
                if ('..' === $part && count($parts) && '..' !== end($parts)) {
                    array_pop($parts);
                } else {
                    $parts[] = $part;
                }
            }

            return str_replace($matches['url'], implode(\DIRECTORY_SEPARATOR, $parts), $matches[0]);
        });

        $asset->setContent($content);
    }
}
