<style>
    /* --- Production 2-Column Wide Grid Layout CSS Adjustments --- */
    .dashboard-header {
        max-width: 1600px;
        margin: 0 auto 15px auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: sans-serif;
        padding: 0 10px;
        color: #24292e;
    }
    .tab-bar {
        display: flex;
        gap: 5px;
    }
    .tab-button {
        padding: 8px 16px;
        background: #e1e4e8;
        border: 1px solid #cbd5e0;
        border-radius: 6px 6px 0 0;
        cursor: pointer;
        font-weight: bold;
        color: #4a5568;
    }
    .tab-button.active {
        background: #ffffff;
        border-bottom: 2px solid #ffffff;
        color: #ef233c;
        position: relative;
        z-index: 5;
    }
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(650px, 1fr)) !important; /* Forces 2 per row layout on desktop grids */
        gap: 20px;
    }
    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr !important; /* Stacks vertically down to 100% on small devices phones */
        }
    }
    .canvas-container {
        height: 240px !important; /* Wide shorter aspect ratio visualization layout look */
    }
    /* Sleek 4-Column Horizontal Metadata Row Formatting */
    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-top: 15px;
        padding-top: 12px;
        border-top: 1px solid #e1e4e8;
        font-family: sans-serif;
        text-align: center;
    }
    .metric-box {
        display: flex;
        flex-direction: column;
    }
    .metric-box small {
        font-size: 0.75rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: bold;
        margin-bottom: 3px;
    }
    .metric-box span {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2d3748;
    }
</style>

