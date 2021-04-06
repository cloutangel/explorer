[common]
; Алфавит для кодирование чисел по словарю
alphabet    = 'Rs85bJvMywcTHVt34xfr26aXgUeCKzGNp7j9ZmFkdYuSBEiQnDWALhPq'
epoch       = 1617703188000
secret      = '3d55b71bbeacd9eb4c0b19312b6d874317b68b40513f72157848cf35e897238b'

trigger_map_file   = '{{CONFIG_DIR}}/trigger_event_map.php'
trigger_param_file = '{{CONFIG_DIR}}/trigger_param_map.php'
uri_map_file	     = '{{CONFIG_DIR}}/uri_request_map.php'
param_map_file     = '{{CONFIG_DIR}}/import_var_map.php'
action_map_file    = '{{CONFIG_DIR}}/action_map.php'

upload_max_filesize = '10M'

proto = 'http'
domain = 'explorer.cloutangle.lo'

lang_type = 'none' ; path or domain or none depends what we use for split
languages[] = 'en'

[common:production]
domain = 'explorer.cloutangel.com'

[default]
action = 'home'

[view]
source_dir          = '{{APP_DIR}}/views'
compile_dir         = '{{TMP_DIR}}/views'
template_extension  = 'tpl'
strip_comments      = false
merge_lines         = false

[view:production]
compile_dir    = '{{TMP_DIR}}/{{PROJECT_REV}}/views'
strip_comments = true
merge_lines    = true

[session]
name          = 'KISS'
save_handler  = 'files'
save_depth    = 2 ; this config used only for handler=files
save_path     = "{{TMP_DIR}}/{{PROJECT_REV}}/sessions"

[nginx]
port = 8000
auth_name = 'test'
auth_pass = 'test'
; auth_basic nginx param: off, Restricted
auth = 'off'
open_file_cache = 'off'

[nginx:production]
open_file_cache = 'max=100000 inactive=600s'

[nginx:test]
auth = 'Restricted'

[cors]
origin = '*'
methods = 'GET, POST, PUT, DELETE, OPTIONS'
headers = 'DNT,X-CustomHeader,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type'
credentials = 'true'
