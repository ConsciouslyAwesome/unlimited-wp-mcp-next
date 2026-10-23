// Generated from the local next-generation WordPress catalog.
export const UPSTREAM_TOOLS = [
  {
    "name": "abilities",
    "description": "Abilities. Uses the original WordPress handler and capability checks. Read operation. Requires manage_options plus the ability permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "manage_options plus the ability permission callback"
    }
  },
  {
    "name": "ability_info",
    "description": "Ability Info. Uses the original WordPress handler and capability checks. Read operation. Requires manage_options plus the ability permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "name": {
          "type": "string"
        }
      },
      "required": [
        "name"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "manage_options plus the ability permission callback"
    }
  },
  {
    "name": "ability_run",
    "description": "Ability Run. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires manage_options plus the ability permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "name": {
          "type": "string"
        },
        "input": {
          "type": "object"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "name",
        "input"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "manage_options plus the ability permission callback"
    }
  },
  {
    "name": "audit_log",
    "description": "Audit Log. Uses the original WordPress handler and capability checks. Read operation. Requires manage_options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "limit": {
          "type": "integer",
          "default": 50,
          "minimum": 1,
          "maximum": 200
        },
        "offset": {
          "type": "integer",
          "default": 0
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "manage_options"
    }
  },
  {
    "name": "beaver_modules",
    "description": "Beaver Modules. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "beaver_save_page",
    "description": "Beaver Save Page. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        },
        "title": {
          "type": "string"
        },
        "post_type": {
          "type": "string",
          "default": "page"
        },
        "status": {
          "type": "string",
          "default": "publish"
        },
        "data": {
          "type": "object"
        },
        "theme_layout_type": {
          "type": "string"
        },
        "theme_layout_locations": {
          "type": "array"
        },
        "theme_layout_settings": {
          "type": "object"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "data"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "beaver_schema",
    "description": "Beaver Schema. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "slug": {
          "type": "string"
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "breakdance_elements",
    "description": "Breakdance Elements. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "breakdance_get_page",
    "description": "Breakdance Get Page. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        }
      },
      "required": [
        "id"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "breakdance_save_page",
    "description": "Breakdance Save Page. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        },
        "title": {
          "type": "string"
        },
        "post_type": {
          "type": "string",
          "default": "page"
        },
        "status": {
          "type": "string",
          "default": "publish",
          "enum": [
            "publish",
            "draft",
            "pending",
            "private"
          ]
        },
        "tree": {
          "type": "object"
        },
        "tree_json_string": {
          "type": "string"
        },
        "template": {
          "type": "string",
          "enum": [
            "blank_canvas",
            "theme"
          ]
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "bricks_elements",
    "description": "Bricks Elements. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "bricks_get_page",
    "description": "Bricks Get Page. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        }
      },
      "required": [
        "id"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "bricks_save_page",
    "description": "Bricks Save Page. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        },
        "title": {
          "type": "string"
        },
        "post_type": {
          "type": "string",
          "default": "page"
        },
        "status": {
          "type": "string",
          "default": "publish",
          "enum": [
            "publish",
            "draft",
            "pending",
            "private"
          ]
        },
        "elements": {
          "type": "array"
        },
        "template_type": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "elements"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "builder_login",
    "description": "Builder Login. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_post for target content plus the builder-specific permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "page_id": {
          "type": "integer"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "page_id"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_post for target content plus the builder-specific permission callback"
    }
  },
  {
    "name": "classic_theme_safety",
    "description": "Classic Theme Safety. Uses the original WordPress handler and capability checks. Read operation. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "challenge": {
          "type": "string",
          "pattern": "^[a-f0-9]{32}$"
        }
      },
      "required": [
        "challenge"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "cli_run",
    "description": "Run an allowlisted WordPress administrative command using native PHP, not a shell. Supports posts, comments, users, roles, plugins, themes, options, transients, database and core. All commands require browser approval; destructive commands include a fresh state preview. Requires manage_options plus the command-specific capability.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "command": {
          "type": "string"
        },
        "confirm_write": {
          "type": "boolean",
          "default": false
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "command"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "manage_options plus the command-specific capability"
    }
  },
  {
    "name": "cli_status",
    "description": "Cli Status. Uses the original WordPress handler and capability checks. Read operation. Requires edit_theme_options.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_theme_options"
    }
  },
  {
    "name": "code_snippet",
    "description": "Code Snippet. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires wpcode_edit_snippets.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "action": {
          "type": "string",
          "default": "create"
        },
        "id": {
          "type": "integer"
        },
        "code": {
          "type": "string"
        },
        "title": {
          "type": "string"
        },
        "code_type": {
          "type": "string"
        },
        "location": {
          "type": "string"
        },
        "insert_method": {
          "type": "string",
          "default": "auto"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "wpcode_edit_snippets"
    }
  },
  {
    "name": "code_snippet_dormant",
    "description": "Code Snippet Dormant. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_post for target content plus the builder-specific permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "action": {
          "type": "string",
          "default": "create"
        },
        "id": {
          "type": "integer"
        },
        "code": {
          "type": "string"
        },
        "title": {
          "type": "string"
        },
        "code_type": {
          "type": "string"
        },
        "location": {
          "type": "string"
        },
        "insert_method": {
          "type": "string",
          "default": "auto"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_post for target content plus the builder-specific permission callback"
    }
  },
  {
    "name": "content_edit",
    "description": "Replace exact text in an authorized post, meta value or allowed option. Requires edit_post for the target post; manage_options for options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "target_type": {
          "type": "string",
          "enum": [
            "post",
            "meta",
            "option"
          ]
        },
        "post_id": {
          "type": "integer"
        },
        "field": {
          "type": "string"
        },
        "meta_key": {
          "type": "string"
        },
        "option_name": {
          "type": "string"
        },
        "old_content": {
          "type": "string"
        },
        "new_content": {
          "type": "string"
        },
        "replace_all": {
          "type": "boolean",
          "default": false
        },
        "whole_word": {
          "type": "boolean",
          "default": false
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "target_type",
        "old_content",
        "new_content"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_post for the target post; manage_options for options"
    }
  },
  {
    "name": "content_search",
    "description": "Content Search. Uses the original WordPress handler and capability checks. Read operation. Requires edit_post for the target post; manage_options for options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "target_type": {
          "type": "string",
          "enum": [
            "post",
            "meta",
            "option"
          ]
        },
        "post_id": {
          "type": "integer"
        },
        "field": {
          "type": "string"
        },
        "meta_key": {
          "type": "string"
        },
        "option_name": {
          "type": "string"
        },
        "pattern": {
          "type": "string"
        },
        "case_sensitive": {
          "type": "boolean"
        },
        "max_results": {
          "type": "integer",
          "minimum": 1,
          "maximum": 200
        }
      },
      "required": [
        "target_type",
        "pattern"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_post for the target post; manage_options for options"
    }
  },
  {
    "name": "create_classic_theme",
    "description": "Create Classic Theme. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "theme_name": {
          "type": "string"
        },
        "description": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "theme_name"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "create_classic_theme_safe",
    "description": "Create Classic Theme Safe. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "theme_name": {
          "type": "string"
        },
        "description": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "theme_name"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "create_draft_theme",
    "description": "Create Draft Theme. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "delete_draft_theme",
    "description": "Delete Draft Theme. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "draft_theme_compile_sources",
    "description": "Draft Theme Compile Sources. Uses the original WordPress handler and capability checks. Read operation. Requires edit_themes (edit_theme_options on a locked single site).",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes (edit_theme_options on a locked single site)"
    }
  },
  {
    "name": "draft_theme_preview",
    "description": "Draft Theme Preview. Uses the original WordPress handler and capability checks. Read operation. Requires edit_themes (edit_theme_options on a locked single site).",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes (edit_theme_options on a locked single site)"
    }
  },
  {
    "name": "draft_theme_publish",
    "description": "Publish the draft theme after safety checks. Replaces live theme files; browser approval required. Requires edit_themes and switch_themes.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "expected_source_hash": {
          "type": "string",
          "pattern": "^[a-f0-9]{64}$"
        },
        "saved_customizations": {
          "type": "string",
          "enum": [
            "set_aside",
            "keep"
          ]
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes and switch_themes"
    }
  },
  {
    "name": "elementor_save_page",
    "description": "Elementor Save Page. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        },
        "title": {
          "type": "string"
        },
        "post_type": {
          "type": "string",
          "default": "page"
        },
        "status": {
          "type": "string"
        },
        "template_type": {
          "type": "string"
        },
        "page_template": {
          "type": "string"
        },
        "data": {
          "type": "array"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "data"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "elementor_save_template",
    "description": "Elementor Save Template. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires manage_options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer"
        },
        "title": {
          "type": "string"
        },
        "type": {
          "type": "string"
        },
        "status": {
          "type": "string"
        },
        "data": {
          "type": "array"
        },
        "conditions": {
          "type": "array",
          "default": [
            "include/general"
          ]
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "type",
        "data"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "manage_options"
    }
  },
  {
    "name": "elementor_schema",
    "description": "Elementor Schema. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "slug": {
          "type": "string"
        },
        "names": {
          "type": "string"
        },
        "prefix": {
          "type": "string"
        }
      },
      "required": [
        "slug"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "elementor_style_schema",
    "description": "Elementor Style Schema. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "names": {
          "type": "string"
        },
        "prefix": {
          "type": "string"
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "elementor_widgets",
    "description": "Elementor Widgets. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "file_delete",
    "description": "File Delete. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "path"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "file_edit",
    "description": "File Edit. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string"
        },
        "old_content": {
          "type": "string"
        },
        "new_content": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "path",
        "old_content",
        "new_content"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "file_list",
    "description": "File List. Uses the original WordPress handler and capability checks. Read operation. Requires edit_themes for theme files; manage_options for wp-content; network administrator on multisite.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "scope": {
          "type": "string",
          "enum": [
            "theme",
            "wp-content"
          ]
        },
        "directory": {
          "type": "string"
        },
        "pattern": {
          "type": "string"
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes for theme files; manage_options for wp-content; network administrator on multisite"
    }
  },
  {
    "name": "file_outline",
    "description": "File Outline. Uses the original WordPress handler and capability checks. Read operation. Requires edit_themes (edit_theme_options on a locked single site).",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string"
        }
      },
      "required": [
        "path"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes (edit_theme_options on a locked single site)"
    }
  },
  {
    "name": "file_read",
    "description": "Read a bounded theme or wp-content text file. Private/configuration files and sandbox escapes are denied. Requires edit_themes for theme files; manage_options for wp-content; network administrator on multisite.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "scope": {
          "type": "string",
          "enum": [
            "theme",
            "wp-content"
          ]
        },
        "path": {
          "type": "string"
        },
        "start_line": {
          "type": "integer"
        },
        "end_line": {
          "type": "integer"
        }
      },
      "required": [
        "path"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes for theme files; manage_options for wp-content; network administrator on multisite"
    }
  },
  {
    "name": "file_search",
    "description": "File Search. Uses the original WordPress handler and capability checks. Read operation. Requires edit_themes (edit_theme_options on a locked single site).",
    "inputSchema": {
      "type": "object",
      "properties": {
        "pattern": {
          "type": "string"
        },
        "case_sensitive": {
          "type": "boolean",
          "default": false
        },
        "extensions": {
          "type": "array"
        },
        "max_results": {
          "type": "integer",
          "default": 100,
          "minimum": 1,
          "maximum": 200
        }
      },
      "required": [
        "pattern"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes (edit_theme_options on a locked single site)"
    }
  },
  {
    "name": "file_write",
    "description": "Write a draft theme file with PHP syntax checks and optional source-hash concurrency protection. Requires edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "expected_source_hash": {
          "type": "string",
          "pattern": "^[a-f0-9]{64}$"
        },
        "path": {
          "type": "string"
        },
        "content": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "path",
        "content"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_themes; respects DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS"
    }
  },
  {
    "name": "last_change",
    "description": "Last Change. Uses the original WordPress handler and capability checks. Read operation. Requires edit_posts.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "since": {
          "type": "number",
          "default": 0
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_posts"
    }
  },
  {
    "name": "navigate",
    "description": "Navigate. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_theme_options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "url": {
          "type": "string"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "url"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_theme_options"
    }
  },
  {
    "name": "operation_receipt",
    "description": "Read the local status of an operation belonging to this authenticated connection. A started operation with no completion is uncertain and must not be blindly retried.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "operation_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$"
        }
      },
      "required": [
        "operation_id"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": false
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "read; same user and connection only"
    }
  },
  {
    "name": "registered_meta",
    "description": "Registered Meta. Uses the original WordPress handler and capability checks. Read operation. Requires edit_theme_options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_type": {
          "type": "string"
        }
      },
      "required": [
        "post_type"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_theme_options"
    }
  },
  {
    "name": "rendered_html",
    "description": "Rendered Html. Uses the original WordPress handler and capability checks. Read operation. Requires edit_theme_options.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string"
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_theme_options"
    }
  },
  {
    "name": "rest_api_read",
    "description": "Rest Api Read. Uses the original WordPress handler and capability checks. Read operation. Requires edit_post for target content plus the builder-specific permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string",
          "pattern": "^/[A-Za-z0-9_-]+/"
        },
        "parameters": {
          "type": "object",
          "default": []
        }
      },
      "required": [
        "path"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_post for target content plus the builder-specific permission callback"
    }
  },
  {
    "name": "rest_api_write",
    "description": "Rest Api Write. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires edit_post for target content plus the builder-specific permission callback.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string",
          "pattern": "^/[A-Za-z0-9_-]+/"
        },
        "parameters": {
          "type": "object",
          "default": []
        },
        "method": {
          "type": "string",
          "enum": [
            "POST",
            "PUT",
            "PATCH",
            "DELETE"
          ]
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "path",
        "method"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_post for target content plus the builder-specific permission callback"
    }
  },
  {
    "name": "site_info",
    "description": "Read site, theme, plugin and authenticated WordPress user information. Requires edit_theme_options.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "edit_theme_options"
    }
  },
  {
    "name": "upload_media",
    "description": "Upload Media. Uses the original WordPress handler and capability checks. Write or potentially destructive operation; requires local browser approval. Requires upload_files.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "url": {
          "type": "string"
        },
        "title": {
          "type": "string"
        },
        "alt_text": {
          "type": "string"
        },
        "post_id": {
          "type": "integer"
        },
        "approval_id": {
          "type": "string",
          "pattern": "^[a-f0-9]{48}$",
          "description": "One-use local browser approval returned by a prior call with these exact arguments."
        }
      },
      "required": [
        "url"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": true,
      "idempotentHint": false,
      "openWorldHint": true
    },
    "_meta": {
      "org.unlimitedwpmcp/permission": "upload_files"
    }
  }
];
