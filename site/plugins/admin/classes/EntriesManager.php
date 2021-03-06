<?php

namespace Flextype;

use Flextype\Component\Arr\Arr;
use Flextype\Component\I18n\I18n;
use Flextype\Component\Http\Http;
use Flextype\Component\Event\Event;
use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Session\Session;
use Flextype\Component\Registry\Registry;
use Flextype\Component\Token\Token;
use Flextype\Component\Text\Text;
use Flextype\Component\Form\Form;
use Flextype\Component\Notification\Notification;
use function Flextype\Component\I18n\__;
use Gajus\Dindent\Indenter;
use Intervention\Image\ImageManagerStatic as Image;

class EntriesManager
{

    public static function getEntriesManager() : void
    {
        Registry::set('sidebar_menu_item', 'entries');

        $query = EntriesManager::getEntriesQuery();

        switch (Http::getUriSegment(2)) {
            case 'add':
                EntriesManager::addEntry();
            break;
            case 'delete':
                EntriesManager::deleteEntry();
            break;
            case 'duplicate':
                EntriesManager::duplicateEntry();
            break;
            case 'rename':
                EntriesManager::renameEntry();
            break;
            case 'type':
                EntriesManager::typeEntry();
            break;
            case 'move':
                EntriesManager::moveEntry();
            break;
            case 'edit':
                EntriesManager::editEntry();
            break;
            default:
                EntriesManager::listEntry();
            break;
        }
    }

    public static function getMediaList(string $entry, bool $path = false) : array
    {
        $files = [];

        foreach (array_diff(scandir(PATH['entries'] . '/' . $entry), ['..', '.']) as $file) {
            if (strpos(Registry::get('settings.entries.media.accept_file_types'), $file_ext = substr(strrchr($file, '.'), 1)) !== false) {
                if (strpos($file, strtolower($file_ext), 1)) {
                    if ($path) {
                        $files[Http::getBaseUrl() . '/' . $entry . '/' . $file] = Http::getBaseUrl() . '/' . $entry . '/' . $file;
                    } else {
                        $files[$file] = $file;
                    }
                }
            }
        }

        return $files;
    }

    protected static function getEntriesQuery() : string
    {
        if (Http::get('entry') && Http::get('entry') != '') {
            $query = Http::get('entry');
        } else {
            $query = '';
        }

        return $query;
    }

    protected static function listEntry() : void
    {
        Themes::view('admin/views/templates/content/entries/list')
            ->assign('entries_list', Entries::fetchAll(EntriesManager::getEntriesQuery(), 'date', 'DESC'))
            ->display();
    }

    protected static function processFilesManager() : void
    {
        $files_directory = PATH['entries'] . '/' . Http::get('entry') . '/';

        if (Http::get('delete_file') != '') {
            if (Token::check((Http::get('token')))) {
                Filesystem::delete($files_directory . Http::get('delete_file'));
                Notification::set('success', __('admin_message_entry_file_deleted'));
                Http::redirect(Http::getBaseUrl() . '/admin/entries/edit?entry=' . Http::get('entry') . '&media=true');
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }

        if (Http::post('upload_file')) {
            if (Token::check(Http::post('token'))) {

                $file = EntriesManager::uploadFile($_FILES['file'], $files_directory, Registry::get('settings.entries.media.accept_file_types'), 27000000);

                if ($file !== false) {

                    if (in_array(pathinfo($file)['extension'], ['jpg', 'jpeg', 'png', 'gif'])) {

                        // open an image file
                        $img = Image::make($file);

                        // now you are able to resize the instance
                        if (Registry::get('settings.entries.media.upload_images_width') > 0 && Registry::get('settings.entries.media.upload_images_height') > 0) {
                            $img->resize(Registry::get('settings.entries.media.upload_images_width'), Registry::get('settings.entries.media.upload_images_height'), function($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                        } elseif (Registry::get('settings.entries.media.upload_images_width') > 0) {
                            $img->resize(Registry::get('settings.entries.media.upload_images_width'), null, function($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                        } elseif (Registry::get('settings.entries.media.upload_images_height') > 0) {
                            $img->resize(null, Registry::get('settings.entries.media.upload_images_height'), function($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                        }

                        // finally we save the image as a new file
                        $img->save($file, Registry::get('settings.entries.media.upload_images_quality'));

                        // destroy
                        $img->destroy();
                    }

                    Notification::set('success', __('admin_message_entry_file_uploaded'));
                    Http::redirect(Http::getBaseUrl() . '/admin/entries/edit?entry=' . Http::get('entry') . '&media=true');
                } else {
                    Notification::set('error', __('admin_message_entry_file_not_uploaded'));
                    Http::redirect(Http::getBaseUrl() . '/admin/entries/edit?entry=' . Http::get('entry') . '&media=true');
                }

            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }
    }

    protected static function editEntry() : void
    {
        $entry = Entries::fetch(Http::get('entry'));

        if (Http::get('media') && Http::get('media') == 'true') {
            EntriesManager::processFilesManager();

            Themes::view('admin/views/templates/content/entries/media')
                ->assign('entry_name', Http::get('entry'))
                ->assign('files', EntriesManager::getMediaList(Http::get('entry')), true)
                ->assign('entry', $entry)
                ->display();
        } else {
            if (Http::get('source') && Http::get('source') == 'true') {

                $action = Http::post('action');

                if (isset($action) && $action == 'save-form') {
                    if (Token::check((Http::post('token')))) {
                        if (Filesystem::write(
                            PATH['entries'] . '/' . Http::post('entry_name') . '/entry.yaml',
                                                    Http::post('entry_content')
                        )) {
                            Notification::set('success', __('admin_message_entry_changes_saved'));
                        } else {
                            Notification::set('success', __('admin_message_entry_changes_not_saved'));
                        }

                        Http::redirect(Http::getBaseUrl() . '/admin/entries/edit?entry=' . Http::post('entry_name') . '&source=true');
                    } else {
                        throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
                    }
                }

                $entry_content = Filesystem::read(PATH['entries'] . '/' . Http::get('entry') . '/entry.yaml');

                Themes::view('admin/views/templates/content/entries/source')
                    ->assign('entry_name', Http::get('entry'))
                    ->assign('entry_content', $entry_content)
                    ->assign('entry', $entry)
                    ->assign('files', EntriesManager::getMediaList(Http::get('entry')), true)
                    ->display();
            } else {

                $action = Http::post('action');
                $indenter = new Indenter();

                if (isset($action) && $action == 'save-form') {
                    if (Token::check((Http::post('token')))) {

                        $entry = Entries::fetch(Http::get('entry'));
                        Arr::delete($entry, 'slug');

                        $data = [];

                        $_data = $_POST;
                        Arr::delete($_data, 'token');
                        Arr::delete($_data, 'action');

                        foreach ($_data as $key => $_d) {
                            $data[$key] = $indenter->indent($_d);
                        }

                        $data = array_merge($entry, $data);

                        if (Entries::update(Http::get('entry'), $data)) {
                            Notification::set('success', __('admin_message_entry_changes_saved'));
                        } else {
                            Notification::set('error', __('admin_message_entry_changes_not_saved'));
                        }

                        Http::redirect(Http::getBaseUrl() . '/admin/entries/edit?entry=' . Http::get('entry'));
                    }
                }

                // Fieldset for current entry template
                $fieldset_path = PATH['themes'] . '/' . Registry::get('settings.theme') . '/fieldsets/' . (isset($entry['fieldset']) ? $entry['fieldset'] : 'default') . '.yaml';
                $fieldset = YamlParser::decode(Filesystem::read($fieldset_path));
                is_null($fieldset) and $fieldset = [];

                Themes::view('admin/views/templates/content/entries/content')
                    ->assign('entry_name', Http::get('entry'))
                    ->assign('entry', $entry)
                    ->assign('fieldset', $fieldset)
                    ->assign('templates', Themes::getTemplates())
                    ->assign('files', EntriesManager::getMediaList(Http::get('entry')), true)
                    ->display();
            }
        }
    }

    protected static function duplicateEntry() : void
    {
        if (Http::get('entry') != '') {
            if (Token::check((Http::get('token')))) {

                if (Entries::copy(Http::get('entry'), Http::get('entry') . '-duplicate-' . date("Ymd_His"), true)) {
                    Notification::set('success', __('admin_message_entry_duplicated'));
                } else {
                    Notification::set('error', __('admin_message_entry_was_not_duplicated'));
                }

                Http::redirect(Http::getBaseUrl() . '/admin/entries/?entry=' . implode('/', array_slice(explode("/", Http::get('entry')), 0, -1)));
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }
    }

    protected static function moveEntry() : void
    {
        $entry = Entries::fetch(Http::get('entry'));

        $move_entry = Http::post('move_entry');

        if (isset($move_entry)) {
            if (Token::check((Http::post('token')))) {
                if (!Entries::has(Http::post('parent_entry') . '/' . Http::post('name_current'))) {
                    if (Entries::rename(
                        Http::post('entry_path_current'),
                        Http::post('parent_entry') . '/' . Text::safeString(Http::post('name_current'), '-', true)
                    )) {
                        Notification::set('success', __('admin_message_entry_moved'));
                    } else {
                        Notification::set('error', __('admin_message_entry_was_not_moved'));
                    }

                    Http::redirect(Http::getBaseUrl() . '/admin/entries/?entry=' . Http::post('parent_entry'));

                }
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }

        $_entries_list = Entries::fetchAll('', 'slug');
        $entries_list['/'] = '/';
        foreach ($_entries_list as $_entry) {
            if ($_entry['slug'] != '') {
                $entries_list[$_entry['slug']] = $_entry['slug'];
            } else {
                $entries_list[Registry::get('settings.entries.main')] = Registry::get('settings.entries.main');
            }
        }

        Themes::view('admin/views/templates/content/entries/move')
            ->assign('entry_path_current', Http::get('entry'))
            ->assign('entries_list', $entries_list)
            ->assign('name_current', Arr::last(explode("/", Http::get('entry'))))
            ->assign('entry_parent', implode('/', array_slice(explode("/", Http::get('entry')), 0, -1)))
            ->assign('entry', $entry)
            ->display();
    }

    protected static function deleteEntry() : void
    {
        if (Http::get('entry') != '') {
            if (Token::check((Http::get('token')))) {

                if (Entries::delete(Http::get('entry'))) {
                    Notification::set('success', __('admin_message_entry_deleted'));
                } else {
                    Notification::set('error', __('admin_message_entry_was_not_deleted'));
                }

                Http::redirect(Http::getBaseUrl() . '/admin/entries/?entry=' . Http::get('entry_current'));
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }
    }

    protected static function renameEntry() : void
    {
        $entry = Entries::fetch(Http::get('entry'));

        $rename_entry = Http::post('rename_entry');

        if (isset($rename_entry)) {
            if (Token::check((Http::post('token')))) {
                if (!Entries::has(Http::post('name'))) {
                    if (Entries::rename(
                        Http::post('entry_path_current'),
                        Http::post('entry_parent') . '/' . Text::safeString(Http::post('name'), '-', true)
                    )) {
                        Notification::set('success', __('admin_message_entry_renamed'));
                    } else {
                        Notification::set('error', __('admin_message_entry_was_not_renamed'));
                    }

                    Http::redirect(Http::getBaseUrl() . '/admin/entries/?entry=' . Http::post('entry_parent'));
                }
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }

        Themes::view('admin/views/templates/content/entries/rename')
            ->assign('name_current', Arr::last(explode("/", Http::get('entry'))))
            ->assign('entry_path_current', Http::get('entry'))
            ->assign('entry_parent', implode('/', array_slice(explode("/", Http::get('entry')), 0, -1)))
            ->assign('entry', $entry)
            ->display();
    }

    protected static function typeEntry() : void
    {
        $type_entry = Http::post('type_entry');

        if (isset($type_entry)) {
            if (Token::check((Http::post('token')))) {

                $entry = Entries::fetch(Http::get('entry'));
                Arr::delete($entry, 'slug');

                $data  = [];
                $_data = $_POST;
                Arr::delete($_data, 'token');
                Arr::delete($_data, 'type_entry');
                Arr::delete($_data, 'entry');

                $data = array_merge($entry, $_data);

                if (Entries::update(Http::get('entry'), $data)) {
                    Notification::set('success', __('admin_message_entry_changes_saved'));
                } else {
                    Notification::set('success', __('admin_message_entry_was_not_moved'));
                }

                Http::redirect(Http::getBaseUrl() . '/admin/entries?entry=' . implode('/', array_slice(explode("/", Http::get('entry')), 0, -1)));
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }

        $entry = Entries::fetch(Http::get('entry'));

        $fieldsets = [];

        // Get fieldsets files
        $_fieldsets = Filesystem::listContents(PATH['themes'] . '/' . Registry::get('settings.theme') . '/fieldsets/');

        // If there is any template file then go...
        if (count($_fieldsets) > 0) {
            foreach ($_fieldsets as $fieldset) {
                if ($fieldset['type'] == 'file' && $fieldset['extension'] == 'yaml') {
                    $fieldset_content = YamlParser::decode(Filesystem::read($fieldset['path']));
                    if (isset($fieldset_content['sections']) && isset($fieldset_content['sections']['main']) && isset($fieldset_content['sections']['main']['fields'])) {
                        $fieldsets[$fieldset['basename']] = $fieldset_content['title'];
                    }
                }
            }
        }

        Themes::view('admin/views/templates/content/entries/type')
            ->assign('fieldset', $entry['fieldset'])
            ->assign('fieldsets', $fieldsets)
            ->display();
    }

    protected static function addEntry() : void
    {
        $create_entry = Http::post('create_entry');

        if (isset($create_entry)) {
            if (Token::check((Http::post('token')))) {

                // Set parent entry
                if (Http::post('parent_entry')) {
                    $parent_entry = '/' . Http::post('parent_entry');
                } else {
                    $parent_entry = '/';
                }

                // Set new entry name
                $entry = $parent_entry . Text::safeString(Http::post('slug'), '-', true);

                // Check if new entry exists
                if (!Entries::has($entry)) {

                    // Get fieldset
                    $fieldset = YamlParser::decode(Filesystem::read(PATH['themes'] . '/' . Registry::get('settings.theme') . '/fieldsets/' . Http::post('fieldset') . '.yaml'));

                    // We need to check if template for current fieldset is exists
                    // if template is not exist then default template will be used!
                    $template_path = PATH['themes'] . '/' . Registry::get('settings.theme') . '/views/templates/' . Http::post('fieldset') . '.php';
                    if (Filesystem::has($template_path)) {
                        $template = Http::post('fieldset');
                    } else {
                        $template = 'default';
                    }

                    // Init entry data
                    $data = [];
                    $default_data = [];

                    // Define data values based on POST data
                    $default_data['title']     = Http::post('title');
                    $default_data['template']  = $template;
                    $default_data['fieldset']  = Http::post('fieldset');
                    $default_data['date']      = date(Registry::get('settings.date_format'), time());

                    // Predefine data values based on selected fieldset
                    foreach ($fieldset['sections'] as $section) {
                        foreach ($section as $key => $field) {

                            // Get values from default data
                            if (isset($default_data[$key])) {

                                $_value = $default_data[$key];

                            // Get values from fieldsets predefined field values
                            } elseif (isset($field['value'])) {

                                $_value = $field['value'];

                            // or set empty value
                            } else {
                                $_value = '';
                            }

                            $data[$key] = $_value;
                        }
                    }

                    // Merge data
                    $data = array_replace_recursive($data, $default_data);

                    // Create a new entry!
                    if (Entries::create($entry, $data)) {
                        Notification::set('success', __('admin_message_entry_created'));
                    } else {
                        Notification::set('success', __('admin_message_entry_was_not_created'));
                    }

                    Http::redirect(Http::getBaseUrl() . '/admin/entries/?entry=' . Http::post('parent_entry'));
                }
            } else {
                throw new \RuntimeException("Request was denied because it contained an invalid security token. Please refresh the page and try again.");
            }
        }

        $fieldsets = [];

        // Get fieldsets files
        $_fieldsets = Filesystem::listContents(PATH['themes'] . '/' . Registry::get('settings.theme') . '/fieldsets/');

        // If there is any template file then go...
        if (count($_fieldsets) > 0) {
            foreach ($_fieldsets as $fieldset) {
                if ($fieldset['type'] == 'file' && $fieldset['extension'] == 'yaml') {
                    $fieldset_content = YamlParser::decode(Filesystem::read($fieldset['path']));
                    if (isset($fieldset_content['sections']) && isset($fieldset_content['sections']['main']) && isset($fieldset_content['sections']['main']['fields'])) {
                        $fieldsets[$fieldset['basename']] = $fieldset_content['title'];
                    }
                }
            }
        }

        Themes::view('admin/views/templates/content/entries/add')
            ->assign('fieldsets', $fieldsets)
            ->assign('entries_list', Entries::fetchAll('', 'slug'))
            ->display();
    }

    /**
     * Upload files on the Server with several type of Validations!
     *
     * Entries::uploadFile($_FILES['file'], $files_directory);
     *
     * @param   array   $file             Uploaded file data
     * @param   string  $upload_directory Upload directory
     * @param   string  $allowed          Allowed file extensions
     * @param   int     $max_size         Max file size in bytes
     * @param   string  $filename         New filename
     * @param   bool    $remove_spaces    Remove spaces from the filename
     * @param   int     $max_width        Maximum width of image
     * @param   int     $max_height       Maximum height of image
     * @param   bool    $exact            Match width and height exactly?
     * @param   int     $chmod            Chmod mask
     * @return  string  on success, full path to new file
     * @return  false   on failure
     */
    public static function uploadFile(
        array $file,
                                        string $upload_directory,
                                        string $allowed = 'jpeg, png, gif, jpg',
                                        int $max_size = 3000000,
                                        string $filename = null,
                                        bool $remove_spaces = true,
                                        int $max_width = null,
                                        int $max_height = null,
                                        bool $exact = false,
                                        int $chmod = 0644
    ) {
        //
        // Tests if a successful upload has been made.
        //
        if (isset($file['error'])
            and isset($file['tmp_name'])
            and $file['error'] === UPLOAD_ERR_OK
            and is_uploaded_file($file['tmp_name'])) {

            //
            // Tests if upload data is valid, even if no file was uploaded.
            //
            if (isset($file['error'])
                    and isset($file['name'])
                    and isset($file['type'])
                    and isset($file['tmp_name'])
                    and isset($file['size'])) {

                //
                // Test if an uploaded file is an allowed file type, by extension.
                //
                if (strpos($allowed, strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) !== false) {

                    //
                    // Validation rule to test if an uploaded file is allowed by file size.
                    //
                    if (($file['error'] != UPLOAD_ERR_INI_SIZE)
                                  and ($file['error'] == UPLOAD_ERR_OK)
                                  and ($file['size'] <= $max_size)) {

                        //
                        // Validation rule to test if an upload is an image and, optionally, is the correct size.
                        //
                        if (in_array(mime_content_type($file['tmp_name']), ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
                            function validateImage($file, $max_width, $max_height, $exact)
                            {
                                try {
                                    // Get the width and height from the uploaded image
                                    list($width, $height) = getimagesize($file['tmp_name']);
                                } catch (ErrorException $e) {
                                    // Ignore read errors
                                }

                                if (empty($width) or empty($height)) {
                                    // Cannot get image size, cannot validate
                                    return false;
                                }

                                if (!$max_width) {
                                    // No limit, use the image width
                                    $max_width = $width;
                                }

                                if (!$max_height) {
                                    // No limit, use the image height
                                    $max_height = $height;
                                }

                                if ($exact) {
                                    // Check if dimensions match exactly
                                    return ($width === $max_width and $height === $max_height);
                                } else {
                                    // Check if size is within maximum dimensions
                                    return ($width <= $max_width and $height <= $max_height);
                                }

                                return false;
                            }

                            if (validateImage($file, $max_width, $max_height, $exact) === false) {
                                return false;
                            }
                        }

                        if (!isset($file['tmp_name']) or !is_uploaded_file($file['tmp_name'])) {

                            // Ignore corrupted uploads
                            return false;
                        }

                        if ($filename === null) {

                            // Use the default filename
                            $filename = $file['name'];
                        }

                        if ($remove_spaces === true) {

                            // Remove spaces from the filename
                            $filename = Text::safeString(pathinfo($filename)['filename'], '-', true) . '.' . pathinfo($filename)['extension'];
                        }

                        if (!is_dir($upload_directory) or !is_writable(realpath($upload_directory))) {
                            throw new \RuntimeException("Directory {$upload_directory} must be writable");
                        }

                        // Make the filename into a complete path
                        $filename = realpath($upload_directory) . DIRECTORY_SEPARATOR . $filename;

                        if (move_uploaded_file($file['tmp_name'], $filename)) {

                            // Set permissions on filename
                            chmod($filename, $chmod);

                            // Return new file path
                            return $filename;
                        }
                    }
                }
            }
        }

        return false;
    }

}
