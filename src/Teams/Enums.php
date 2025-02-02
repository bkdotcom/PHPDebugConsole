<?php

/**
 * @package   bdk\teams
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Teams;

/**
 * Adaptive card enum values
 */
class Enums
{
    const ACTION_MODE_PRIMARY = 'primary';
    const ACTION_MODE_SECONDARY = 'secondary';

    // action style
    const ACTION_STYLE_DEFAULT = 'default';
    const ACTION_STYLE_DESTRUCTIVE = 'destructive';
    const ACTION_STYLE_POSITIVE = 'positive';

    const ACTION_TYPE_IMBACK = 'imBack';
    const ACTION_TYPE_INVOKE = 'invoke';
    const ACTION_TYPE_MESSAGEBACK = 'messageBack';
    const ACTION_TYPE_OPENURL = 'openUrl';
    const ACTION_TYPE_SIGNIN = 'signin';

    // block element height
    const HEIGHT_AUTO = 'auto';
    const HEIGHT_STRETCH = 'stretch';

    // colors
    const COLOR_ACCENT = 'accent';
    const COLOR_ATTENTION = 'attention';
    const COLOR_DARK = 'dark';
    const COLOR_DEFAULT = 'default';
    const COLOR_GOOD = 'good';
    const COLOR_LIGHT = 'light';
    const COLOR_WARNING = 'warning';

    // column width
    const COLUMN_WIDTH_AUTO = 'auto';
    const COLUMN_WIDTH_STRETCH = 'stretch';

    const CONTAINER_STYLE_ACCENT = 'accent';
    const CONTAINER_STYLE_ATTENTION = 'attention'; // Added in version 1.2.
    const CONTAINER_STYLE_DEFAULT = 'default';
    const CONTAINER_STYLE_EMPHASIS = 'emphasis';
    const CONTAINER_STYLE_GOOD = 'good'; // Added in version 1.2.
    const CONTAINER_STYLE_WARNING = 'warning'; // Added in version 1.2.

    const FALLBACK_DROP = 'drop';

    // fillmode (used for background image)
    const FILLMODE_COVER = 'cover';
    const FILLMODE_REPEAT = 'repeat';
    const FILLMODE_REPEAT_X = 'repeatHorizontally';
    const FILLMODE_REPEAT_Y = 'repeatVertically';

    // font size
    const FONT_SIZE_DEFAULT = 'default';
    const FONT_SIZE_EXTRA_LARGE = 'extraLarge';
    const FONT_SIZE_LARGE = 'large';
    const FONT_SIZE_MEDIUM = 'medium';
    const FONT_SIZE_SMALL = 'small';

    // font type
    const FONT_TYPE_DEFAULT = 'default';
    const FONT_TYPE_MONOSPACE = 'monospace';

    // font weight
    const FONT_WEIGHT_BOLDER = 'bolder';
    const FONT_WEIGHT_DEFAULT = 'default';
    const FONT_WEIGHT_LIGHTER = 'lighter';

    // horizontal alignment
    const HORIZONTAL_ALIGNMENT_CENTER = 'center';
    const HORIZONTAL_ALIGNMENT_LEFT = 'left';
    const HORIZONTAL_ALIGNMENT_RIGHT = 'right';

    // image size
    const IMAGE_SIZE_AUTO = 'auto';
    const IMAGE_SIZE_LARGE = 'large';
    const IMAGE_SIZE_MEDIUM = 'medium';
    const IMAGE_SIZE_SMALL = 'small';
    const IMAGE_SIZE_STRETCH = 'stretch';

    // image style
    const IMAGE_STYLE_DEFAULT = 'default';
    const IMAGE_STYLE_PERSON = 'person';

    // spacing
    const SPACING_DEFAULT = 'default';
    const SPACING_EXTRA_LARGE = 'extraLarge';
    const SPACING_LARGE = 'large';
    const SPACING_MEDIUM = 'medium';
    const SPACING_NONE = 'none';
    const SPACING_PADDING = 'padding';
    const SPACING_SMALL = 'small';

    const TEXTBLOCK_STYLE_DEFAULT = 'default';
    const TEXTBLOCK_STYLE_HEADING = 'heading';

    // horizontal alignment
    const VERTICAL_ALIGNMENT_TOP = 'top';
    const VERTICAL_ALIGNMENT_CENTER = 'center';
    const VERTICAL_ALIGNMENT_BOTTOM = 'bottom';
}
