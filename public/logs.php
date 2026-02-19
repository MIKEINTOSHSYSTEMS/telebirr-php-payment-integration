<?php

/**
 * API Logs Viewer Page
 * Displays detailed API call logs from the database
 */

$page_title = 'API Logs Viewer - Telebirr Payment Gateway';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/header.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

// Get filter parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 50;
$endpoint = $_GET['endpoint'] ?? '';
$method = $_GET['method'] ?? '';
$statusCode = $_GET['status_code'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build filters array
$filters = [];
if (!empty($endpoint)) $filters['endpoint'] = $endpoint;
if (!empty($method)) $filters['method'] = $method;
if (!empty($statusCode)) $filters['status_code'] = $statusCode;
if (!empty($dateFrom)) $filters['date_from'] = $dateFrom . ' 00:00:00';
if (!empty($dateTo)) $filters['date_to'] = $dateTo . ' 23:59:59';
if (!empty($search)) $filters['search'] = $search;

// Get logs from database
$logs = $telebirr->getApiLogs($page, $perPage, $filters);

// Get unique methods and status codes for filter dropdowns
$stats = $telebirr->getLogStats();

// Function to format JSON nicely for display
function formatJsonForDisplay($json)
{
    if (empty($json)) return '<em class="text-muted">Empty</em>';

    // Try to decode and re-encode with pretty print
    $data = json_decode($json, true);
    if ($data === null) {
        return htmlspecialchars($json);
    }

    $formatted = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return '<pre class="json-formatter">' . htmlspecialchars($formatted) . '</pre>';
}

// Function to get status badge class
function getStatusBadgeClass($code)
{
    if ($code >= 200 && $code < 300) return 'status-completed';
    if ($code >= 300 && $code < 400) return 'status-processing';
    if ($code >= 400 && $code < 500) return 'status-failed';
    if ($code >= 500) return 'status-error';
    return 'status-unknown';
}
?>

<style>
    /* Logs Page Specific Styles */
    :root {
        --json-key: #9c27b0;
        --json-string: #4caf50;
        --json-number: #2196f3;
        --json-boolean: #ff9800;
        --json-null: #f44336;
    }

    [data-theme="dark"] {
        --json-key: #ce93d8;
        --json-string: #81c784;
        --json-number: #64b5f6;
        --json-boolean: #ffb74d;
        --json-null: #e57373;
    }

    .logs-header {
        margin-bottom: 30px;
    }

    .logs-header h1 {
        font-size: 2.5em;
        margin-bottom: 10px;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--bg-card);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--border-light);
        text-align: center;
    }

    .stat-value {
        font-size: 2.5em;
        font-weight: bold;
        color: var(--primary-color);
        line-height: 1;
        margin-bottom: 5px;
    }

    .stat-label {
        color: var(--text-secondary);
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .filters-section {
        background: var(--bg-card);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--border-light);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        font-size: 0.85em;
        font-weight: 600;
        margin-bottom: 5px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-group input,
    .filter-group select {
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        background: var(--bg-secondary);
        color: var(--text-primary);
        font-size: 0.95em;
    }

    .filter-group input:focus,
    .filter-group select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        justify-content: flex-end;
    }

    .logs-table-container {
        background: var(--bg-card);
        border-radius: var(--border-radius-lg);
        padding: 20px;
        box-shadow: var(--box-shadow);
        border: 1px solid var(--border-light);
        overflow-x: auto;
    }

    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }

    .logs-table th {
        background: var(--bg-secondary);
        padding: 15px 10px;
        text-align: left;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .logs-table td {
        padding: 15px 10px;
        border-bottom: 1px solid var(--border-light);
        vertical-align: top;
    }

    .logs-table tr:hover {
        background: var(--bg-secondary);
    }

    .log-row {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .log-row:hover {
        transform: translateX(5px);
        box-shadow: var(--box-shadow);
    }

    .log-row.expanded {
        background: var(--bg-secondary);
    }

    .log-details {
        background: var(--bg-tertiary);
        padding: 20px;
        border-radius: var(--border-radius-md);
        margin-top: 10px;
    }

    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .details-card {
        background: var(--bg-card);
        border-radius: var(--border-radius-md);
        padding: 15px;
        border: 1px solid var(--border-light);
    }

    .details-card h4 {
        margin: 0 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-light);
        color: var(--text-primary);
        font-size: 1.1em;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .details-card h4 i {
        font-size: 1.2em;
    }

    .details-card pre {
        margin: 0;
        font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        font-size: 0.9em;
        line-height: 1.5;
        color: var(--text-primary);
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .json-formatter {
        background: var(--bg-tertiary);
        padding: 15px;
        border-radius: var(--border-radius-sm);
        overflow-x: auto;
        font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
        font-size: 0.85em;
        line-height: 1.5;
        color: var(--text-primary);
        margin: 0;
    }

    /* JSON Syntax Highlighting */
    .json-key {
        color: var(--json-key);
    }

    .json-string {
        color: var(--json-string);
    }

    .json-number {
        color: var(--json-number);
    }

    .json-boolean {
        color: var(--json-boolean);
    }

    .json-null {
        color: var(--json-null);
    }

    .method-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .method-GET {
        background: #61affe;
        color: white;
    }

    .method-POST {
        background: #49cc90;
        color: white;
    }

    .method-PUT {
        background: #fca130;
        color: white;
    }

    .method-DELETE {
        background: #f93e3e;
        color: white;
    }

    .method-PATCH {
        background: #50e3c2;
        color: white;
    }

    .endpoint-cell {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .timestamp {
        color: var(--text-secondary);
        font-size: 0.9em;
        white-space: nowrap;
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 30px;
        flex-wrap: wrap;
    }

    .pagination-info {
        color: var(--text-secondary);
        font-size: 0.95em;
    }

    .pagination-controls {
        display: flex;
        gap: 5px;
    }

    .pagination-btn {
        padding: 8px 15px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        border-radius: var(--border-radius-md);
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .pagination-btn:hover:not(.disabled) {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .pagination-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .expand-icon {
        display: inline-block;
        transition: transform 0.2s ease;
        font-size: 1.2em;
    }

    .expanded .expand-icon {
        transform: rotate(90deg);
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-secondary);
    }

    .empty-state svg {
        width: 100px;
        height: 100px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .clear-filters {
        color: var(--primary-color);
        text-decoration: none;
        font-size: 0.9em;
    }

    .clear-filters:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .details-grid {
            grid-template-columns: 1fr;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .logs-table th:nth-child(4),
        .logs-table td:nth-child(4) {
            display: none;
        }
    }
</style>

<div class="container">
    <div class="logs-header">
        <h1>📋 API Logs Viewer</h1>
        <p class="subtitle">Comprehensive view of all API calls to Telebirr</p>
    </div>

    <!-- Statistics Cards -->
    <?php if ($stats['success']): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
                <div class="stat-label">Total Logs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['success_count']) ?></div>
                <div class="stat-label">Successful</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['error_count']) ?></div>
                <div class="stat-label">Errors</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['unique_endpoints']) ?></div>
                <div class="stat-label">Endpoints</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" id="filterForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="endpoint">Endpoint</label>
                    <input type="text" id="endpoint" name="endpoint" placeholder="e.g., /payment/v1/token" value="<?= htmlspecialchars($endpoint) ?>">
                </div>

                <div class="filter-group">
                    <label for="method">Method</label>
                    <select id="method" name="method">
                        <option value="">All Methods</option>
                        <?php if ($stats['success'] && !empty($stats['methods'])): ?>
                            <?php foreach ($stats['methods'] as $m): ?>
                                <option value="<?= $m ?>" <?= $method == $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status_code">Status Code</label>
                    <select id="status_code" name="status_code">
                        <option value="">All Status</option>
                        <?php if ($stats['success'] && !empty($stats['status_codes'])): ?>
                            <?php foreach ($stats['status_codes'] as $code): ?>
                                <option value="<?= $code ?>" <?= $statusCode == $code ? 'selected' : '' ?>><?= $code ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>

                <div class="filter-group">
                    <label for="search">Search in Data</label>
                    <input type="text" id="search" name="search" placeholder="Search request/response..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="logs.php" class="btn btn-outline">Clear Filters</a>
                <button type="button" class="btn btn-secondary" onclick="exportLogs()">Export CSV</button>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="logs-table-container">
        <?php if ($logs['success'] && !empty($logs['data'])): ?>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th style="width: 30px"></th>
                        <th>Time</th>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs['data'] as $log): ?>
                        <tr class="log-row" onclick="toggleLogDetails(<?= $log['id'] ?>)">
                            <td><span class="expand-icon" id="expand-<?= $log['id'] ?>">▶</span></td>
                            <td class="timestamp"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                            <td>
                                <span class="method-badge method-<?= $log['method'] ?>"><?= htmlspecialchars($log['method']) ?></span>
                            </td>
                            <td class="endpoint-cell" title="<?= htmlspecialchars($log['endpoint']) ?>">
                                <?= htmlspecialchars($log['endpoint']) ?>
                            </td>
                            <td>
                                <span class="badge <?= getStatusBadgeClass($log['status_code']) ?>">
                                    <?= htmlspecialchars($log['status_code']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                            <td>
                                <button class="btn btn-small" onclick="event.stopPropagation(); copyLogData(<?= $log['id'] ?>)">Copy</button>
                            </td>
                        </tr>
                        <tr id="details-<?= $log['id'] ?>" style="display: none;">
                            <td colspan="7">
                                <div class="log-details">
                                    <div class="details-grid">
                                        <div class="details-card">
                                            <h4><span>📤 Request</span></h4>
                                            <?= formatJsonForDisplay($log['request_data']) ?>
                                        </div>
                                        <div class="details-card">
                                            <h4><span>📥 Response</span></h4>
                                            <?= formatJsonForDisplay($log['response_data']) ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                                        <small class="text-muted">
                                            <strong>ID:</strong> <?= $log['id'] ?> |
                                            <strong>User Agent:</strong> <?= htmlspecialchars($log['user_agent'] ?? 'Unknown') ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?= (($logs['pagination']['current_page'] - 1) * $logs['pagination']['per_page']) + 1 ?>
                    to <?= min($logs['pagination']['current_page'] * $logs['pagination']['per_page'], $logs['pagination']['total']) ?>
                    of <?= number_format($logs['pagination']['total']) ?> entries
                </div>
                <div class="pagination-controls">
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>"
                        class="pagination-btn <?= $logs['pagination']['current_page'] <= 1 ? 'disabled' : '' ?>">First</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $logs['pagination']['current_page'] - 1])) ?>"
                        class="pagination-btn <?= $logs['pagination']['current_page'] <= 1 ? 'disabled' : '' ?>">Previous</a>

                    <?php
                    $start = max(1, $logs['pagination']['current_page'] - 2);
                    $end = min($logs['pagination']['total_pages'], $logs['pagination']['current_page'] + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                            class="pagination-btn <?= $i == $logs['pagination']['current_page'] ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $logs['pagination']['current_page'] + 1])) ?>"
                        class="pagination-btn <?= $logs['pagination']['current_page'] >= $logs['pagination']['total_pages'] ? 'disabled' : '' ?>">Next</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $logs['pagination']['total_pages']])) ?>"
                        class="pagination-btn <?= $logs['pagination']['current_page'] >= $logs['pagination']['total_pages'] ? 'disabled' : '' ?>">Last</a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M20 12H4M12 4v16M4 4h16v16H4z" stroke-linecap="round" />
                </svg>
                <h3>No logs found</h3>
                <p>No API logs match your filters. Try adjusting your search criteria.</p>
                <?php if (!empty(array_filter($_GET))): ?>
                    <a href="logs.php" class="clear-filters">Clear all filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle log details
    function toggleLogDetails(id) {
        const detailsRow = document.getElementById('details-' + id);
        const expandIcon = document.getElementById('expand-' + id);
        const logRow = detailsRow.previousElementSibling;

        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
            expandIcon.innerHTML = '▼';
            logRow.classList.add('expanded');
        } else {
            detailsRow.style.display = 'none';
            expandIcon.innerHTML = '▶';
            logRow.classList.remove('expanded');
        }
    }

    // Copy log data to clipboard
    function copyLogData(id) {
        const detailsRow = document.getElementById('details-' + id);
        const requestData = detailsRow.querySelector('.details-card:first-child .json-formatter')?.textContent || '';
        const responseData = detailsRow.querySelector('.details-card:last-child .json-formatter')?.textContent || '';

        const textToCopy = `=== REQUEST ===\n${requestData}\n\n=== RESPONSE ===\n${responseData}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            if (window.TelebirrPayment && window.TelebirrPayment.showNotification) {
                window.TelebirrPayment.showNotification('Log data copied to clipboard!', 'success');
            }
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }

    // Export logs to CSV
    function exportLogs() {
        const form = document.getElementById('filterForm');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();

        window.location.href = 'export-logs.php?' + params;
    }

    // Add JSON syntax highlighting
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.json-formatter').forEach(element => {
            let html = element.innerHTML;
            // Add syntax highlighting classes
            html = html.replace(/"([^"]+)":/g, '<span class="json-key">"$1"</span>:');
            html = html.replace(/: "([^"]*)"/g, ': <span class="json-string">"$1"</span>');
            html = html.replace(/: (\d+)/g, ': <span class="json-number">$1</span>');
            html = html.replace(/: (true|false)/g, ': <span class="json-boolean">$1</span>');
            html = html.replace(/: (null)/g, ': <span class="json-null">$1</span>');
            element.innerHTML = html;
        });
    });

    // Add loading indicator for filters
    document.getElementById('filterForm').addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-small"></span> Applying...';

            // Add spinner styles if not present
            if (!document.getElementById('spinner-styles')) {
                const style = document.createElement('style');
                style.id = 'spinner-styles';
                style.textContent = `
                .spinner-small {
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    border: 2px solid rgba(255,255,255,0.3);
                    border-radius: 50%;
                    border-top-color: #fff;
                    animation: spin 1s ease-in-out infinite;
                    margin-right: 8px;
                    vertical-align: middle;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            `;
                document.head.appendChild(style);
            }
        }
    });

    // Auto-refresh toggle
    let autoRefreshInterval = null;
    const autoRefreshCheckbox = document.createElement('input');
    autoRefreshCheckbox.type = 'checkbox';
    autoRefreshCheckbox.id = 'autoRefresh';
    autoRefreshCheckbox.style.marginLeft = '10px';

    const autoRefreshLabel = document.createElement('label');
    autoRefreshLabel.htmlFor = 'autoRefresh';
    autoRefreshLabel.textContent = ' Auto-refresh (30s)';
    autoRefreshLabel.style.fontSize = '0.9em';
    autoRefreshLabel.style.color = 'var(--text-secondary)';

    // Add to filter actions
    const filterActions = document.querySelector('.filter-actions');
    if (filterActions) {
        const refreshContainer = document.createElement('div');
        refreshContainer.style.display = 'flex';
        refreshContainer.style.alignItems = 'center';
        refreshContainer.style.marginLeft = 'auto';
        refreshContainer.appendChild(autoRefreshCheckbox);
        refreshContainer.appendChild(autoRefreshLabel);
        filterActions.appendChild(refreshContainer);
    }

    autoRefreshCheckbox.addEventListener('change', function() {
        if (this.checked) {
            autoRefreshInterval = setInterval(() => {
                location.reload();
            }, 30000); // 30 seconds
        } else {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    });

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Press 'r' to refresh
        if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
            location.reload();
        }

        // Press 'f' to focus search
        if (e.key === 'f' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            document.getElementById('search')?.focus();
        }

        // Press 'Esc' to clear all filters
        if (e.key === 'Escape') {
            const clearBtn = document.querySelector('a[href="logs.php"]');
            if (clearBtn && confirm('Clear all filters?')) {
                window.location.href = clearBtn.href;
            }
        }
    });

    // tooltips for better UX
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.position = 'absolute';
            tooltip.style.background = 'var(--bg-tertiary)';
            tooltip.style.color = 'var(--text-primary)';
            tooltip.style.padding = '5px 10px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '0.85rem';
            tooltip.style.zIndex = '1000';
            tooltip.style.pointerEvents = 'none';
            tooltip.style.boxShadow = 'var(--box-shadow)';
            tooltip.style.border = '1px solid var(--border-color)';

            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            }, {
                once: true
            });
        });
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>