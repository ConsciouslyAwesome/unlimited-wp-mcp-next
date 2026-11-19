// Extracted without executing handlers from GPL WPVibe 1.20.5 class-wpvibe-rest.php, commit 62bc77ccd1c15faf766f527a7935fe32f1b227d3.
export const BASELINE_ROUTES = {
  "/site-info": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/registered-meta": {
    "method": "GET",
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
    }
  },
  "/file/read": {
    "method": "POST",
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
    }
  },
  "/file/list": {
    "method": "GET",
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
    }
  },
  "/file/search": {
    "method": "POST",
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
          "default": 100
        }
      },
      "required": [
        "pattern"
      ],
      "additionalProperties": false
    }
  },
  "/file/outline": {
    "method": "POST",
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
    }
  },
  "/file/edit": {
    "method": "POST",
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
        }
      },
      "required": [
        "path",
        "old_content",
        "new_content"
      ],
      "additionalProperties": false
    }
  },
  "/file/write": {
    "method": "POST",
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
        }
      },
      "required": [
        "path",
        "content"
      ],
      "additionalProperties": false
    }
  },
  "/file/delete": {
    "method": "POST",
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
    }
  },
  "/content/edit": {
    "method": "POST",
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
        }
      },
      "required": [
        "target_type",
        "old_content",
        "new_content"
      ],
      "additionalProperties": false
    }
  },
  "/content/search": {
    "method": "POST",
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
          "type": "integer"
        }
      },
      "required": [
        "target_type",
        "pattern"
      ],
      "additionalProperties": false
    }
  },
  "/classic-theme-safety": {
    "method": "GET",
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
    }
  },
  "/draft-theme/compile-sources": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/draft-theme": {
    "method": "DELETE",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/draft-theme/publish": {
    "method": "POST",
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
        }
      },
      "required": [],
      "additionalProperties": false
    }
  },
  "/draft-theme/preview": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/draft-theme/delete": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/cli/run": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "command": {
          "type": "string"
        },
        "confirm_write": {
          "type": "boolean",
          "default": false
        }
      },
      "required": [
        "command"
      ],
      "additionalProperties": false
    }
  },
  "/audit-log": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {
        "limit": {
          "type": "integer",
          "default": 50
        },
        "offset": {
          "type": "integer",
          "default": 0
        }
      },
      "required": [],
      "additionalProperties": false
    }
  },
  "/audit-log/record": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "operation": {
          "type": "string"
        },
        "command": {
          "type": "string"
        },
        "params": {
          "type": "string"
        },
        "dry_run": {
          "type": "string"
        },
        "result_summary": {
          "type": "string"
        }
      },
      "required": [
        "operation",
        "command"
      ],
      "additionalProperties": false
    }
  },
  "/op-receipt/(?P<op_id>[a-z0-9_.:]+)": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {
        "op_id": {
          "type": "string"
        }
      },
      "required": [
        "op_id"
      ],
      "additionalProperties": false
    }
  },
  "/cli/run-approved": {
    "method": "POST",
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
        "approved_state": {
          "type": "string"
        },
        "detach": {
          "type": "boolean",
          "default": false
        }
      },
      "required": [
        "command"
      ],
      "additionalProperties": false
    }
  },
  "/authorize": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "name": {
          "type": "string"
        },
        "app_id": {
          "type": "string"
        }
      },
      "required": [
        "name"
      ],
      "additionalProperties": false
    }
  },
  "/authorize/preflight": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/connection-status": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/op-proof/check": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "challenge": {
          "type": "string",
          "maxLength": 80
        }
      },
      "required": [
        "challenge"
      ],
      "additionalProperties": false
    }
  },
  "/code-snippet": {
    "method": "POST",
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
        }
      },
      "required": [],
      "additionalProperties": false
    }
  },
  "/code-snippet/dormant": {
    "method": "POST",
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
        }
      },
      "required": [],
      "additionalProperties": false
    }
  },
  "/cli/status": {
    "method": "GET",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": [],
      "additionalProperties": false
    }
  },
  "/builder-login": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "page_id": {
          "type": "integer"
        }
      },
      "required": [
        "page_id"
      ],
      "additionalProperties": false
    }
  },
  "/rendered-html": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string"
        }
      },
      "required": [],
      "additionalProperties": false
    }
  },
  "/create-classic-theme": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "theme_name": {
          "type": "string"
        },
        "description": {
          "type": "string"
        }
      },
      "required": [
        "theme_name"
      ],
      "additionalProperties": false
    }
  },
  "/create-classic-theme-safe": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "theme_name": {
          "type": "string"
        },
        "description": {
          "type": "string"
        }
      },
      "required": [
        "theme_name"
      ],
      "additionalProperties": false
    }
  },
  "/upload-media": {
    "method": "POST",
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
        }
      },
      "required": [
        "url"
      ],
      "additionalProperties": false
    }
  },
  "/last-change": {
    "method": "GET",
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
    }
  },
  "/navigate": {
    "method": "POST",
    "inputSchema": {
      "type": "object",
      "properties": {
        "url": {
          "type": "string"
        }
      },
      "required": [
        "url"
      ],
      "additionalProperties": false
    }
  }
};
