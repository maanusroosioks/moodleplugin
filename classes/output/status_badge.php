<?php
namespace mod_idetestfeedback\output;

use renderable;
use renderer_base;
use templatable;

class status_badge implements renderable, templatable {

    /**
     * Bootstrap background utility for each known status.
     *
     * ERROR shares the danger background with FAILED and is set apart by a
     * plugin CSS class, so no colour literal is needed here.
     *
     * @var array<string, string>
     */
    private const BACKGROUNDS = [
        'PASSED' => 'bg-success',
        'FAILED' => 'bg-danger',
        'ERROR' => 'bg-danger',
        'SKIPPED' => 'bg-secondary',
    ];

    /**
     * Constructor.
     *
     * @param string $status one of PASSED, FAILED, ERROR or SKIPPED; anything
     *      else falls back to the neutral background
     */
    public function __construct(
        protected readonly string $status
    ) {
    }

    /**
     * Exports the badge for mod_idetestfeedback/status_badge.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $classes = (self::BACKGROUNDS[$this->status] ?? 'bg-secondary') . ' text-white';

        if ($this->status === 'ERROR') {
            $classes .= ' idetestfeedback-badge-error';
        }

        return [
            'label' => $this->status,
            'classes' => $classes,
        ];
    }
}
