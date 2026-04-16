import { EditorView } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { basicSetup } from 'codemirror';
import { sql } from '@codemirror/lang-sql';
import { oneDark } from '@codemirror/theme-one-dark';
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';
import sqlHljs from 'highlight.js/lib/languages/sql';

hljs.registerLanguage('json', json);
hljs.registerLanguage('sql', sqlHljs);

import 'highlight.js/styles/github-dark-dimmed.min.css';

/**
 * @param {EditorView} view
 * @param {string} doc
 */
function replaceDocument(view, doc) {
    const current = view.state.doc.toString();
    if (current === doc) {
        return;
    }
    view.dispatch({
        changes: { from: 0, to: view.state.doc.length, insert: doc },
    });
}

/**
 * @param {HTMLTextAreaElement | null} textarea
 * @param {EditorView} view
 */
function syncFromTextarea(textarea, view) {
    if (!textarea) {
        return;
    }
    replaceDocument(view, textarea.value);
}

/**
 * @param {HTMLElement} host
 * @param {HTMLTextAreaElement | null} textarea
 * @returns {EditorView | null}
 */
function mountSqlEditor(host, textarea) {
    if (!textarea) {
        return null;
    }

    const view = new EditorView({
        state: EditorState.create({
            doc: textarea.value,
            extensions: [
                basicSetup,
                sql(),
                oneDark,
                EditorView.theme({
                    '&': { maxHeight: 'min(50vh, 28rem)' },
                    '.cm-scroller': { overflow: 'auto' },
                    '.cm-content': { fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace', fontSize: '13px' },
                }),
                EditorView.updateListener.of((update) => {
                    if (!update.docChanged) {
                        return;
                    }
                    const next = update.state.doc.toString();
                    if (textarea.value !== next) {
                        textarea.value = next;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }),
            ],
        }),
        parent: host,
    });

    host._cmSqlView = view;

    return view;
}

function bootSqlEditor() {
    const host = document.querySelector('[data-cm-sql-host]');
    const textarea = document.getElementById('workspace-sql-textarea');

    if (!host || host._cmSqlView || !textarea) {
        return;
    }

    mountSqlEditor(host, textarea);
}

function syncSqlEditorFromLivewire() {
    const host = document.querySelector('[data-cm-sql-host]');
    const textarea = document.getElementById('workspace-sql-textarea');
    const view = host?._cmSqlView;

    if (!view || !textarea) {
        return;
    }

    syncFromTextarea(textarea, view);
}

/**
 * JSON / SQL blocks in tables and index definitions.
 *
 * @param {ParentNode} [root]
 */
export function highlightCodeBlocks(root = document) {
    root.querySelectorAll('code[data-table-hl]').forEach((el) => {
        if (!(el instanceof HTMLElement) || el.dataset.highlighted === 'yes') {
            return;
        }
        try {
            hljs.highlightElement(el);
        } catch {
            // leave escaped plain text
        }
    });
}

export function initWorkspaceEditors() {
    bootSqlEditor();
    highlightCodeBlocks();
}

document.addEventListener('DOMContentLoaded', initWorkspaceEditors);
document.addEventListener('livewire:navigated', initWorkspaceEditors);

document.addEventListener('livewire:init', () => {
    if (typeof window.Livewire === 'undefined') {
        return;
    }

    window.Livewire.hook('morph.updated', () => {
        queueMicrotask(() => {
            bootSqlEditor();
            syncSqlEditorFromLivewire();
            highlightCodeBlocks();
        });
    });
});
