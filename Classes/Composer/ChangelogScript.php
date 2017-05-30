<?php
namespace BK2K\BootstrapPackage\Composer;

/*
 *  The MIT License (MIT)
 *
 *  Copyright (c) 2015 Benjamin Kott, http://www.bk2k.info
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

use Composer\Script\Event as ScriptEvent;

/**
 * ChangelogScript
 */
class ChangelogScript
{
    /**
     * Generate changelog files
     *
     * @param ScriptEvent $event
     * @throws \RuntimeException
     */
    public static function generateChangelog(ScriptEvent $event)
    {
        if (!function_exists('shell_exec')) {
            throw new \RuntimeException('Please enable shell_exec and rerun this script.', 1496153199);
        }

        // Validate Arguments
        $arguments = $event->getArguments();
        if (count($arguments) === 0) {
            throw new \RuntimeException('No arguments provided. Example: composer run-script changelog 1.0.0', 1496156350);
        }
        if (!preg_match('/\A\d+\.\d+\.\d+\z/', $arguments[0])) {
            throw new \RuntimeException('No valid version number provided! Example: composer run-script changelog 1.0.0', 1496156351);
        }
        $nextVersion = $arguments[0];

        $tags = static::getTags();
        $revisionRanges = static::getRevisionRanges($tags);
        $logs = static::getLogs($tags, $revisionRanges);

        static::generateMarkdown($logs, $nextVersion);
        static::generateRst($logs, $nextVersion);
    }

    /**
     * Gernerating markdown file
     *
     * @param array $logs
     * @param string $nextVersion
     * @throws \RuntimeException
     */
    public static function generateMarkdown($logs, $nextVersion)
    {
        // Prepare content
        $content = '';
        foreach ($logs as $version => $groups) {
            if ($version === 'HEAD') {
                $version = $nextVersion;
            }
            $content .= '# ' . $version . "\n";
            foreach ($groups as $group => $commits) {
                if (is_array($commits) && count($commits) > 0) {
                    $content .= "\n## " . $group . "\n";
                    foreach ($commits as $commit) {
                        $content .= '- ' . strip_tags($commit['message']) . ' ' . $commit['hash'] . "\n";
                    }
                }
            }
            $content .= "\n";
        }

        // Write file
        $file = fopen('CHANGELOG.md', 'w+');
        if (!$file) {
            throw new \RuntimeException('Unable to create CHANGELOG.md', 1496156839);
        }
        fwrite($file, $content);
        fclose($file);
    }

    /**
     * Gernerating RST file
     *
     * @param array $logs
     * @param string $nextVersion
     * @throws \RuntimeException
     */
    public static function generateRst($logs, $nextVersion)
    {
        $charactersVersion = 10;
        $charactersChanges = 170;

        // Prepare content
        $content = '';
        $content .= ".. ==================================================\n";
        $content .= ".. FOR YOUR INFORMATION\n";
        $content .= ".. --------------------------------------------------\n";
        $content .= ".. -*- coding: utf-8 -*- with BOM.\n";
        $content .= "\n";
        $content .= ".. include:: ../../Includes.txt\n";
        $content .= "\n";
        $content .= "\n";
        $content .= ".. _changelog:\n";
        $content .= "\n";
        $content .= "Changelog\n";
        $content .= "---------\n";
        $content .= "\n";
        $content .= "The changelog represents the commits that have been done since the last version\n";
        $content .= "excluding followups. Please have a look at the release notes to get more detailed\n";
        $content .= "Information.\n";
        $content .= "\n";
        $content .= ".. tabularcolumns:: |r|p{13.7cm}|\n";
        $content .= "\n";
        $content .= '+' . static::generateLine('-', $charactersVersion, true) . '+' . static::generateLine('-', $charactersChanges, true) . "+\n";
        $content .= '| Version' . static::generateLine(' ', $charactersVersion - strlen('Version')) . ' ';
        $content .= '| Changes' . static::generateLine(' ', $charactersChanges - strlen('Changes')) . ' ';
        $content .= "|\n";
        $content .= '+' . static::generateLine('-', $charactersVersion, true) . '+' . static::generateLine('-', $charactersChanges, true) . "+\n";
        foreach ($logs as $version => $groups) {
            $isFirst = true;
            foreach ($groups as $group => $commits) {
                if (is_array($commits) && count($commits) > 0) {
                    foreach ($commits as $commit) {
                        $content .= '|';
                        if ($isFirst === true) {
                            $isFirst = false;
                            if ($version === 'HEAD') {
                                $version = $nextVersion;
                            }
                            $content .= ' ' . $version . static::generateLine(' ', $charactersVersion - strlen($version)) . ' ';
                        } else {
                            $content .= static::generateLine(' ', $charactersVersion, true);
                        }
                        $message = strip_tags($commit['message']);
                        $content .= '| ';
                        $content .= '- ' . $message . static::generateLine(' ', $charactersChanges - strlen('- ' . $message));
                        $content .= " |\n";
                    }
                }
            }
            $content .= '+' . static::generateLine('-', $charactersVersion, true) . '+' . static::generateLine('-', $charactersChanges, true) . "+\n";
        }

        // Write file
        $file = fopen('Documentation/AdministratorManual/ChangeLog/Index.rst', 'w+');
        if (!$file) {
            throw new \RuntimeException('Unable to create Documentation/AdministratorManual/ChangeLog/Index.rst', 1496157101);
        }
        fwrite($file, $content);
        fclose($file);
    }

    /**
     * Generate line
     *
     * @param string $character
     * @param int $count
     * @param bool $fill
     * @return string
     */
    public static function generateLine($character = ' ', $count = 0, $fill = false)
    {
        $output = '';
        if ($fill) {
            $count += 2;
        }
        while ($count > 0) {
            $output .= $character;
            $count--;
        }
        return $output;
    }

    /**
     * Filter Logs
     *
     * @param array $logs
     * @return array
     */
    public static function filterLogs($logs)
    {
        $blacklist = [
            'Set version to',
            'Merge pull request',
            'Merge branch',
            'Scrutinizer Auto-Fixer',
            '[FOLLOWUP]',
            '[RELEASE]',
        ];
        foreach ($logs as $version => $entries) {
            foreach ($entries['MISC'] as $logKey => $log) {
                $blacklisted = false;
                foreach ($blacklist as $blacklistedValue) {
                    if (strstr($log['message'], $blacklistedValue)) {
                        $blacklisted = true;
                        unset($logs[$version]['MISC'][$logKey]);
                    }
                }
                if ($blacklisted) {
                    continue;
                }
                if (strstr($log['message'], '!!!')) {
                    $logs[$version]['BREAKING'][] = $log;
                    unset($logs[$version]['MISC'][$logKey]);
                }
                if (strstr($log['message'], '[BUGFIX]')) {
                    $logs[$version]['BUGFIX'][] = $log;
                    unset($logs[$version]['MISC'][$logKey]);
                } elseif (strstr($log['message'], '[TASK]')) {
                    $logs[$version]['TASK'][] = $log;
                    unset($logs[$version]['MISC'][$logKey]);
                } elseif (strstr($log['message'], '[FEATURE]')) {
                    $logs[$version]['FEATURE'][] = $log;
                    unset($logs[$version]['MISC'][$logKey]);
                } elseif (strstr($log['message'], '[RELEASE]')) {
                    $logs[$version]['RELEASE'][] = $log;
                    unset($logs[$version]['MISC'][$logKey]);
                }
            }
        }
        return $logs;
    }

    /**
     * Get all git logs and sort them by tag
     *
     * @param array $tags
     * @param array $revisionRanges
     * @throws \RuntimeException
     * @return array
     */
    public static function getLogs($tags, $revisionRanges)
    {
        if (count($tags) === 0) {
            throw new \RuntimeException('Does not have any tags.', 1496158152);
        }
        $splitChar = '###SPLIT###';
        $logs = [];
        foreach ($revisionRanges as $revisionRange) {
            $query = $revisionRange['end'] . (isset($revisionRange['start']) ? '...' . $revisionRange['start'] : '');
            $format = [
                '%h',
                '%an',
                '%s',
                '%aD',
                '%at'
            ];
            $command = 'git log --pretty="' . implode($splitChar, $format) . '" ' . $query;
            $commits = static::shellOutputToArray(shell_exec($command));
            $formattedCommits = [];
            foreach ($commits as $key => $value) {
                $formattedCommit = explode($splitChar, $value);
                $formattedCommits[] = [
                    'hash' => $formattedCommit[0],
                    'date' => $formattedCommit[3],
                    'timestamp' => $formattedCommit[4],
                    'author' => $formattedCommit[1],
                    'message' => $formattedCommit[2]
                ];
            }
            $logs[$revisionRange['end']] = [
                'RELEASE' => [],
                'BREAKING' => [],
                'FEATURE' => [],
                'TASK' => [],
                'BUGFIX' => [],
                'MISC' => $formattedCommits
            ];
        }
        return static::filterLogs($logs);
    }

    /**
     * Get all git tags
     *
     * @return array
     */
    public static function getTags()
    {
        $tags = static::shellOutputToArray(shell_exec('git tag -l --sort=-v:refname --merged'));
        array_unshift($tags, 'HEAD');
        return $tags;
    }

    /**
     * Get revision ranges
     *
     * @param array $tags
     * @return array
     */
    public static function getRevisionRanges($tags)
    {
        $previous = null;
        $revisionRanges = [];
        foreach ($tags as $key => $value) {
            if (substr($value, 0, 1) !== 'v') {
                if (!is_null($previous)) {
                    $revisionRanges[$previous]['start'] = $value;
                }
                $revisionRanges[$key]['end'] = $value;
                $previous = $key;
            }
        }
        return $revisionRanges;
    }

    /**
     * Output to array
     *
     * @param string $output
     * @return array
     */
    public static function shellOutputToArray($output)
    {
        return array_filter(explode("\n", $output));
    }
}
