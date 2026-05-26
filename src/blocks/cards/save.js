/**
 * Save callback for `bridge/cards`.
 *
 * Fully dynamic block — render.php produces all frontend HTML and
 * <ServerSideRender> uses the same render.php for the editor preview.
 * Nothing is serialised between the block comments.
 */
const Save = () => null;

export default Save;
