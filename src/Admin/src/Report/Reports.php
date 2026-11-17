<?php

declare(strict_types=1);

namespace Admin\Report;

/**
 * The reports that show one dimension in a table, like Open Web Analytics' dimension reports. Each is shown by
 * ReportHandler at /reports/{id}, and listed in the sidebar under its group.
 *
 * - dimension: a key of ReportModel::DIMENSIONS
 * - chart:     "donut" for a share of the whole, "bar" for an ordered series such as hours, or null
 * - link:      "page" links rows to the page report, "host" to the referring site
 * - map:       true for a world map beside the chart, for a dimension of two letter country codes
 */
final class Reports
{
    public const GROUPS = [
        'Visitors' => 'bi-people',
        'Traffic'  => 'bi-signpost-split',
        'Content'  => 'bi-file-earmark-text',
        'Actions'  => 'bi-lightning',
    ];

    /**
     * @var array<string, array{
     *     title: string,
     *     group: string,
     *     dimension: string,
     *     label: string,
     *     description: string,
     *     chart?: string|null,
     *     link?: string|null,
     *     map?: bool
     * }>
     */
    public const ALL = [
        // Visitors
        'locations'          => [
            'title'       => 'Locations',
            'group'       => 'Visitors',
            'dimension'   => 'country',
            'label'       => 'Country',
            'description' => 'The countries visits came from.',
            'chart'       => 'donut',
            'map'         => true,
        ],
        'languages'          => [
            'title'       => 'Languages',
            'group'       => 'Visitors',
            'dimension'   => 'language',
            'label'       => 'Language',
            'description' => 'The languages visitors\' browsers are set to.',
            'chart'       => 'donut',
        ],
        'browsers'           => [
            'title'       => 'Browsers',
            'group'       => 'Visitors',
            'dimension'   => 'browser',
            'label'       => 'Browser',
            'description' => 'The web browsers visitors used.',
            'chart'       => 'donut',
        ],
        'browser-versions'   => [
            'title'       => 'Browser Versions',
            'group'       => 'Visitors',
            'dimension'   => 'browser_version',
            'label'       => 'Browser',
            'description' => 'The web browsers visitors used, by version.',
        ],
        'operating-systems'  => [
            'title'       => 'Operating Systems',
            'group'       => 'Visitors',
            'dimension'   => 'os',
            'label'       => 'Operating System',
            'description' => 'The operating systems visitors used.',
            'chart'       => 'donut',
        ],
        'devices'            => [
            'title'       => 'Devices',
            'group'       => 'Visitors',
            'dimension'   => 'device_type',
            'label'       => 'Device',
            'description' => 'Whether visitors used a computer, phone or tablet.',
            'chart'       => 'donut',
        ],
        'screen-resolutions' => [
            'title'       => 'Screen Resolutions',
            'group'       => 'Visitors',
            'dimension'   => 'screen_resolution',
            'label'       => 'Screen Resolution',
            'description' => 'The sizes of visitors\' screens.',
        ],
        'loyalty'            => [
            'title'       => 'Visitor Loyalty',
            'group'       => 'Visitors',
            'dimension'   => 'visit_number',
            'label'       => 'Visit Number',
            'description' => 'How many times visitors had been before: 1 is their first visit.',
            'chart'       => 'bar',
        ],
        'recency'            => [
            'title'       => 'Visitor Recency',
            'group'       => 'Visitors',
            'dimension'   => 'days_since_last_visit',
            'label'       => 'Days Since Last Visit',
            'description' => 'How long returning visitors had been away.',
            'chart'       => 'bar',
        ],
        'visit-times'        => [
            'title'       => 'Visit Times',
            'group'       => 'Visitors',
            'dimension'   => 'visit_hour',
            'label'       => 'Hour of Day',
            'description' => 'The time of day visits started, in the reporting timezone.',
            'chart'       => 'bar',
        ],
        // Traffic
        'traffic-sources' => [
            'title'       => 'Traffic Sources',
            'group'       => 'Traffic',
            'dimension'   => 'medium',
            'label'       => 'Medium',
            'description' => 'How visitors arrived: directly, from a search engine, another site, a social network '
                . 'or a campaign.',
            'chart'       => 'donut',
        ],
        'sources'         => [
            'title'       => 'All Sources',
            'group'       => 'Traffic',
            'dimension'   => 'source',
            'label'       => 'Source',
            'description' => 'Every search engine, site, network and campaign source visits came from.',
        ],
        'referring-sites' => [
            'title'       => 'Referring Sites',
            'group'       => 'Traffic',
            'dimension'   => 'referrer_host',
            'label'       => 'Site',
            'description' => 'Other websites that linked visitors here.',
            'link'        => 'host',
        ],
        'search-engines'  => [
            'title'       => 'Search Engines',
            'group'       => 'Traffic',
            'dimension'   => 'search_engine',
            'label'       => 'Search Engine',
            'description' => 'Visits from search results.',
            'chart'       => 'donut',
        ],
        'search-terms'    => [
            'title'       => 'Search Terms',
            'group'       => 'Traffic',
            'dimension'   => 'search_terms',
            'label'       => 'Search Terms',
            'description' => 'What visitors searched for, where the search engine or campaign passed it on. Most '
                . 'search engines no longer do.',
        ],
        'social-networks' => [
            'title'       => 'Social Networks',
            'group'       => 'Traffic',
            'dimension'   => 'social_network',
            'label'       => 'Network',
            'description' => 'Visits from social networks.',
            'chart'       => 'donut',
        ],
        'campaigns'       => [
            'title'       => 'Campaigns',
            'group'       => 'Traffic',
            'dimension'   => 'campaign',
            'label'       => 'Campaign',
            'description' => 'Visits from links tagged with utm_campaign, utm_source and utm_medium.',
        ],
        // Content
        'pages'       => [
            'title'       => 'Pages',
            'group'       => 'Content',
            'dimension'   => 'page',
            'label'       => 'Page',
            'description' => 'Every page viewed.',
            'link'        => 'page',
        ],
        'page-titles' => [
            'title'       => 'Page Titles',
            'group'       => 'Content',
            'dimension'   => 'page_title',
            'label'       => 'Title',
            'description' => 'Page views grouped by the pages\' titles.',
        ],
        'entry-pages' => [
            'title'       => 'Entry Pages',
            'group'       => 'Content',
            'dimension'   => 'entry_page',
            'label'       => 'Page',
            'description' => 'The first page of each visit.',
            'link'        => 'page',
        ],
        'exit-pages'  => [
            'title'       => 'Exit Pages',
            'group'       => 'Content',
            'dimension'   => 'exit_page',
            'label'       => 'Page',
            'description' => 'The last page of each visit.',
            'link'        => 'page',
        ],
        // Actions
        'event-categories' => [
            'title'       => 'Event Categories',
            'group'       => 'Actions',
            'dimension'   => 'event_category',
            'label'       => 'Category',
            'description' => 'Events the site reported with trackEvent(), by category.',
            'chart'       => 'donut',
        ],
        'events'           => [
            'title'       => 'Events',
            'group'       => 'Actions',
            'dimension'   => 'event_action',
            'label'       => 'Action',
            'description' => 'Events by category and action.',
        ],
        'event-labels'     => [
            'title'       => 'Event Labels',
            'group'       => 'Actions',
            'dimension'   => 'event_label',
            'label'       => 'Label',
            'description' => 'Events by their label, such as the file downloaded.',
        ],
    ];

    /**
     * @return array<string, array<string, string>> Group => report id => title
     */
    public static function navigation(): array
    {
        $groups = [];
        foreach (self::ALL as $id => $report) {
            $groups[$report['group']][$id] = $report['title'];
        }

        return $groups;
    }
}
