<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Displays enrolment LTI instances.
 *
 * @package    enrol_lticoursetemplate
 * @copyright  2016 Mark Nelson <markn@moodle.com> 2017 Arek Juszczyk <arek.juszczyk@ed.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lticoursetemplate;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Handles displaying enrolment LTI instances.
 *
 * @package    enrol_lticoursetemplate
 * @copyright  2016 Mark Nelson <markn@moodle.com> 2017 Arek Juszczyk <arek.juszczyk@ed.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_table extends \table_sql {

    /**
     * @var \enrol_plugin $ltiplugin
     */
    protected $ltiplugin;

    /**
     * @var bool $ltienabled
     */
    protected $ltienabled;

    /**
     * @var bool $canconfig
     */
    protected $canconfig;

    /**
     * @var int $courseid The course id.
     */
    protected $courseid;

    /**
     * Sets up the table.
     *
     * @param string $courseid The id of the course.
     */
    public function __construct($courseid) {
        parent::__construct('enrol_lticoursetemplate_manage_table');

        $this->define_columns(array(
            'name',
            'url',
            'secret',
            'edit'
        ));
        $this->define_headers(array(
            get_string('name'),
            get_string('url'),
            get_string('secret', 'enrol_lticoursetemplate'),
            get_string('edit')
        ));
        $this->collapsible(false);
        $this->sortable(false);

        // Set the variables we need access to.
        $this->ltiplugin = enrol_get_plugin('lticoursetemplate');
        $this->ltienabled = enrol_is_enabled('lticoursetemplate');
        $this->canconfig = has_capability('moodle/course:enrolconfig', \context_course::instance($courseid));
        $this->courseid = $courseid;
    }

    /**
     * Generate the name column.
     *
     * @param \stdClass $tool event data.
     * @return string
     */
    public function col_name($tool) {
        if (empty($tool->name)) {
            $toolcontext = \context::instance_by_id($tool->contextid);
            $name = $toolcontext->get_context_name();
        } else {
            $name = $tool->name;
        };

        return $this->get_display_text($tool, $name);
    }

    /**
     * Generate the URL column.
     *
     * @param \stdClass $tool event data.
     * @return string
     */
    public function col_url($tool) {
        $url = new \moodle_url('/enrol/lticoursetemplate/tooltemplate.php', array('id' => $tool->id));
        return $this->get_display_text($tool, $url);
    }

    /**
     * Generate the secret column.
     *
     * @param \stdClass $tool event data.
     * @return string
     */
    public function col_secret($tool) {
        return $this->get_display_text($tool, $tool->secret);
    }


    /**
     * Generate the edit column.
     *
     * @param \stdClass $tool event data.
     * @return string
     */
    public function col_edit($tool) {
        global $OUTPUT;

        $buttons = array();

        $instance = new \stdClass();
        $instance->id = $tool->enrolid;
        $instance->courseid = $tool->courseid;
        $instance->enrol = 'lticoursetemplate';
        $instance->status = $tool->status;

        $strdelete = get_string('delete');
        $strenable = get_string('enable');
        $strdisable = get_string('disable');

        $url = new \moodle_url('/enrol/lticoursetemplate/index.php', array('sesskey' => sesskey(), 'courseid' => $this->courseid));

        if ($this->ltiplugin->can_delete_instance($instance)) {
            $aurl = new \moodle_url($url, array('action' => 'delete', 'instanceid' => $instance->id));
            $buttons[] = $OUTPUT->action_icon($aurl, new \pix_icon('t/delete', $strdelete, 'core',
                array('class' => 'iconsmall')));
        }

        if ($this->ltienabled && $this->ltiplugin->can_hide_show_instance($instance)) {
            if ($instance->status == ENROL_INSTANCE_ENABLED) {
                $aurl = new \moodle_url($url, array('action' => 'disable', 'instanceid' => $instance->id));
                $buttons[] = $OUTPUT->action_icon($aurl, new \pix_icon('t/hide', $strdisable, 'core',
                    array('class' => 'iconsmall')));
            } else if ($instance->status == ENROL_INSTANCE_DISABLED) {
                $aurl = new \moodle_url($url, array('action' => 'enable', 'instanceid' => $instance->id));
                $buttons[] = $OUTPUT->action_icon($aurl, new \pix_icon('t/show', $strenable, 'core',
                    array('class' => 'iconsmall')));
            }
        }

        if ($this->ltienabled && $this->canconfig) {
            $linkparams = array(
                'courseid' => $instance->courseid,
                'id' => $instance->id, 'type' => $instance->enrol,
                'returnurl' => new \moodle_url('/enrol/lticoursetemplate/index.php', array('courseid' => $this->courseid))
            );
            $editlink = new \moodle_url("/enrol/editinstance.php", $linkparams);
            $buttons[] = $OUTPUT->action_icon($editlink, new \pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return implode(' ', $buttons);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $total = \enrol_lticoursetemplate\helper::count_lti_tools(array('courseid' => $this->courseid));
        $this->pagesize($pagesize, $total);
        $tools = \enrol_lticoursetemplate\helper::get_lti_tools(array('courseid' => $this->courseid), $this->get_page_start(),
            $this->get_page_size());
        $this->rawdata = $tools;
        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     * Returns text to display in the columns.
     *
     * @param \stdClass $tool the tool
     * @param string $text the text to alter
     * @return string
     */
    protected function get_display_text($tool, $text) {
        if ($tool->status != ENROL_INSTANCE_ENABLED) {
            return \html_writer::tag('span', $text, array('class' => 'dimmed_text'));
        }

        return $text;
    }
}