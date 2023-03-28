<?php

namespace bdk\Teams;

/**
 * Adaptive card enum values
 */
class Enums
{
    public const ACTION_MODE_PRIMARY = 'primary';
    public const ACTION_MODE_SECONDARY = 'secondary';

    // action style
    public const ACTION_STYLE_DEFAULT = 'default';
    public const ACTION_STYLE_DESTRUCTIVE = 'destructive';
    public const ACTION_STYLE_POSITIVE = 'positive';

    public const ACTION_TYPE_IMBACK = 'imBack';
    public const ACTION_TYPE_INVOKE = 'invoke';
    public const ACTION_TYPE_MESSAGEBACK = 'messageBack';
    public const ACTION_TYPE_OPENURL = 'openUrl';
    public const ACTION_TYPE_SIGNIN = 'signin';

    // block element height
    public const HEIGHT_AUTO = 'auto';
    public const HEIGHT_STRETCH = 'stretch';

    // colors
    public const COLOR_ACCENT = 'accent';
    public const COLOR_ATTENTION = 'attention';
    public const COLOR_DARK = 'dark';
    public const COLOR_DEFAULT = 'default';
    public const COLOR_GOOD = 'good';
    public const COLOR_LIGHT = 'light';
    public const COLOR_WARNING = 'warning';

    // column width
    public const COLUMN_WIDTH_AUTO = 'auto';
    public const COLUMN_WIDTH_STRETCH = 'stretch';

    public const CONTAINER_STYLE_ACCENT = 'accent';
    public const CONTAINER_STYLE_ATTENTION = 'attention'; // Added in version 1.2.
    public const CONTAINER_STYLE_DEFAULT = 'default';
    public const CONTAINER_STYLE_EMPHASIS = 'emphasis';
    public const CONTAINER_STYLE_GOOD = 'good'; // Added in version 1.2.
    public const CONTAINER_STYLE_WARNING = 'warning'; // Added in version 1.2.

    public const FALLBACK_DROP = 'drop';

    // fillmode (used for background image)
    public const FILLMODE_COVER = 'cover';
    public const FILLMODE_REPEAT = 'repeat';
    public const FILLMODE_REPEAT_X = 'repeatHorizontally';
    public const FILLMODE_REPEAT_Y = 'repeatVertically';

    // font size
    public const FONT_SIZE_DEFAULT = 'default';
    public const FONT_SIZE_EXTRA_LARGE = 'extraLarge';
    public const FONT_SIZE_LARGE = 'large';
    public const FONT_SIZE_MEDIUM = 'medium';
    public const FONT_SIZE_SMALL = 'small';

    // font type
    public const FONT_TYPE_DEFAULT = 'default';
    public const FONT_TYPE_MONOSPACE = 'monospace';

    // font weight
    public const FONT_WEIGHT_BOLDER = 'bolder';
    public const FONT_WEIGHT_DEFAULT = 'default';
    public const FONT_WEIGHT_LIGHTER = 'lighter';

    // horizontal alignment
    public const HORIZONTAL_ALIGNMENT_CENTER = 'center';
    public const HORIZONTAL_ALIGNMENT_LEFT = 'left';
    public const HORIZONTAL_ALIGNMENT_RIGHT = 'right';

    // image size
    public const IMAGE_SIZE_AUTO = 'auto';
    public const IMAGE_SIZE_LARGE = 'large';
    public const IMAGE_SIZE_MEDIUM = 'medium';
    public const IMAGE_SIZE_SMALL = 'small';
    public const IMAGE_SIZE_STRETCH = 'stretch';

    // image style
    public const IMAGE_STYLE_DEFAULT = 'default';
    public const IMAGE_STYLE_PERSON = 'person';

    // spacing
    public const SPACING_DEFAULT = 'default';
    public const SPACING_EXTRA_LARGE = 'extraLarge';
    public const SPACING_LARGE = 'large';
    public const SPACING_MEDIUM = 'medium';
    public const SPACING_NONE = 'none';
    public const SPACING_PADDING = 'padding';
    public const SPACING_SMALL = 'small';

    public const TEXTBLOCK_STYLE_DEFAULT = 'default';
    public const TEXTBLOCK_STYLE_HEADING = 'heading';

    // horizontal alignment
    public const VERTICAL_ALIGNMENT_TOP = 'top';
    public const VERTICAL_ALIGNMENT_CENTER = 'center';
    public const VERTICAL_ALIGNMENT_BOTTOM = 'bottom';
}
