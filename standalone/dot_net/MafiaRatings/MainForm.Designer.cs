namespace MafiaRatings
{
    partial class MainForm
    {
        /// <summary>
        /// Required designer variable.
        /// </summary>
        private System.ComponentModel.IContainer components = null;

        /// <summary>
        /// Clean up any resources being used.
        /// </summary>
        /// <param name="disposing">true if managed resources should be disposed; otherwise, false.</param>
        protected override void Dispose(bool disposing)
        {
            if (disposing && (components != null))
            {
                components.Dispose();
            }
            base.Dispose(disposing);
        }

        #region Windows Form Designer generated code

        /// <summary>
        /// Required method for Designer support - do not modify
        /// the contents of this method with the code editor.
        /// </summary>
        private void InitializeComponent()
        {
            this.components = new System.ComponentModel.Container();
            System.Windows.Forms.ToolTip toolTip;
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(MainForm));
            this.nextButton = new System.Windows.Forms.Button();
            this.backButton = new System.Windows.Forms.Button();
            this.endButton = new System.Windows.Forms.Button();
            this.toolStrip = new System.Windows.Forms.ToolStrip();
            this.loginMenuItem = new System.Windows.Forms.ToolStripButton();
            this.connectMenuItem = new System.Windows.Forms.ToolStripButton();
            this.optionsButton = new System.Windows.Forms.ToolStripButton();
            this.lastResponseButton = new System.Windows.Forms.ToolStripButton();
            this.disconnectMenuItem = new System.Windows.Forms.ToolStripButton();
            this.refreshButton = new System.Windows.Forms.ToolStripButton();
            this.statusStrip = new System.Windows.Forms.StatusStrip();
            this.statusLabel = new System.Windows.Forms.ToolStripStatusLabel();
            this.buttonsPanel = new System.Windows.Forms.Panel();
            this.contentPanel = new System.Windows.Forms.Panel();
            toolTip = new System.Windows.Forms.ToolTip(this.components);
            this.toolStrip.SuspendLayout();
            this.statusStrip.SuspendLayout();
            this.buttonsPanel.SuspendLayout();
            this.SuspendLayout();
            // 
            // nextButton
            // 
            resources.ApplyResources(this.nextButton, "nextButton");
            this.nextButton.BackColor = System.Drawing.SystemColors.Control;
            this.nextButton.ForeColor = System.Drawing.Color.Black;
            this.nextButton.Name = "nextButton";
            toolTip.SetToolTip(this.nextButton, resources.GetString("nextButton.ToolTip"));
            this.nextButton.UseVisualStyleBackColor = false;
            this.nextButton.Click += new System.EventHandler(this.nextButton_Click);
            // 
            // backButton
            // 
            resources.ApplyResources(this.backButton, "backButton");
            this.backButton.BackColor = System.Drawing.SystemColors.Control;
            this.backButton.ForeColor = System.Drawing.Color.Black;
            this.backButton.Name = "backButton";
            toolTip.SetToolTip(this.backButton, resources.GetString("backButton.ToolTip"));
            this.backButton.UseVisualStyleBackColor = false;
            this.backButton.Click += new System.EventHandler(this.backButton_Click);
            // 
            // endButton
            // 
            resources.ApplyResources(this.endButton, "endButton");
            this.endButton.Name = "endButton";
            toolTip.SetToolTip(this.endButton, resources.GetString("endButton.ToolTip"));
            this.endButton.UseVisualStyleBackColor = true;
            this.endButton.Click += new System.EventHandler(this.endButton_Click);
            // 
            // toolStrip
            // 
            this.toolStrip.GripStyle = System.Windows.Forms.ToolStripGripStyle.Hidden;
            this.toolStrip.Items.AddRange(new System.Windows.Forms.ToolStripItem[] {
            this.loginMenuItem,
            this.connectMenuItem,
            this.optionsButton,
            this.lastResponseButton,
            this.disconnectMenuItem,
            this.refreshButton});
            this.toolStrip.LayoutStyle = System.Windows.Forms.ToolStripLayoutStyle.HorizontalStackWithOverflow;
            resources.ApplyResources(this.toolStrip, "toolStrip");
            this.toolStrip.Name = "toolStrip";
            // 
            // loginMenuItem
            // 
            this.loginMenuItem.DisplayStyle = System.Windows.Forms.ToolStripItemDisplayStyle.Image;
            resources.ApplyResources(this.loginMenuItem, "loginMenuItem");
            this.loginMenuItem.Name = "loginMenuItem";
            this.loginMenuItem.Click += new System.EventHandler(this.loginMenuItem_Click);
            // 
            // connectMenuItem
            // 
            this.connectMenuItem.DisplayStyle = System.Windows.Forms.ToolStripItemDisplayStyle.Image;
            resources.ApplyResources(this.connectMenuItem, "connectMenuItem");
            this.connectMenuItem.Name = "connectMenuItem";
            this.connectMenuItem.Click += new System.EventHandler(this.connectMenuItem_Click);
            // 
            // optionsButton
            // 
            this.optionsButton.Alignment = System.Windows.Forms.ToolStripItemAlignment.Right;
            this.optionsButton.DisplayStyle = System.Windows.Forms.ToolStripItemDisplayStyle.Image;
            resources.ApplyResources(this.optionsButton, "optionsButton");
            this.optionsButton.Name = "optionsButton";
            this.optionsButton.Click += new System.EventHandler(this.optionsButton_Click);
            // 
            // lastResponseButton
            // 
            this.lastResponseButton.Alignment = System.Windows.Forms.ToolStripItemAlignment.Right;
            this.lastResponseButton.DisplayStyle = System.Windows.Forms.ToolStripItemDisplayStyle.Image;
            resources.ApplyResources(this.lastResponseButton, "lastResponseButton");
            this.lastResponseButton.Name = "lastResponseButton";
            this.lastResponseButton.Click += new System.EventHandler(this.lastResponseButton_Click);
            // 
            // disconnectMenuItem
            // 
            this.disconnectMenuItem.DisplayStyle = System.Windows.Forms.ToolStripItemDisplayStyle.Image;
            resources.ApplyResources(this.disconnectMenuItem, "disconnectMenuItem");
            this.disconnectMenuItem.Name = "disconnectMenuItem";
            this.disconnectMenuItem.Click += new System.EventHandler(this.disconnectMenuItem_Click);
            // 
            // refreshButton
            // 
            this.refreshButton.DisplayStyle = System.Windows.Forms.ToolStripItemDisplayStyle.Image;
            resources.ApplyResources(this.refreshButton, "refreshButton");
            this.refreshButton.Name = "refreshButton";
            this.refreshButton.Click += new System.EventHandler(this.refreshButton_Click);
            // 
            // statusStrip
            // 
            this.statusStrip.Items.AddRange(new System.Windows.Forms.ToolStripItem[] {
            this.statusLabel});
            resources.ApplyResources(this.statusStrip, "statusStrip");
            this.statusStrip.Name = "statusStrip";
            this.statusStrip.SizingGrip = false;
            // 
            // statusLabel
            // 
            this.statusLabel.Name = "statusLabel";
            resources.ApplyResources(this.statusLabel, "statusLabel");
            // 
            // buttonsPanel
            // 
            this.buttonsPanel.Controls.Add(this.endButton);
            this.buttonsPanel.Controls.Add(this.contentPanel);
            this.buttonsPanel.Controls.Add(this.nextButton);
            this.buttonsPanel.Controls.Add(this.backButton);
            resources.ApplyResources(this.buttonsPanel, "buttonsPanel");
            this.buttonsPanel.Name = "buttonsPanel";
            // 
            // contentPanel
            // 
            resources.ApplyResources(this.contentPanel, "contentPanel");
            this.contentPanel.Name = "contentPanel";
            // 
            // MainForm
            // 
            resources.ApplyResources(this, "$this");
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.Controls.Add(this.buttonsPanel);
            this.Controls.Add(this.statusStrip);
            this.Controls.Add(this.toolStrip);
            this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedSingle;
            this.MaximizeBox = false;
            this.Name = "MainForm";
            this.FormClosed += new System.Windows.Forms.FormClosedEventHandler(this.MainForm_FormClosed);
            this.Load += new System.EventHandler(this.MainForm_Load);
            this.toolStrip.ResumeLayout(false);
            this.toolStrip.PerformLayout();
            this.statusStrip.ResumeLayout(false);
            this.statusStrip.PerformLayout();
            this.buttonsPanel.ResumeLayout(false);
            this.ResumeLayout(false);
            this.PerformLayout();

        }

        #endregion

        private System.Windows.Forms.ToolStripStatusLabel statusLabel;
        private System.Windows.Forms.ToolStripButton lastResponseButton;
        private System.Windows.Forms.Button nextButton;
        private System.Windows.Forms.Button backButton;
        private System.Windows.Forms.ToolStrip toolStrip;
        private System.Windows.Forms.StatusStrip statusStrip;
        private System.Windows.Forms.Panel buttonsPanel;
        private System.Windows.Forms.Panel contentPanel;
        private System.Windows.Forms.Button endButton;
        private System.Windows.Forms.ToolStripButton refreshButton;
        private System.Windows.Forms.ToolStripButton loginMenuItem;
        private System.Windows.Forms.ToolStripButton connectMenuItem;
        private System.Windows.Forms.ToolStripButton disconnectMenuItem;
        private System.Windows.Forms.ToolStripButton optionsButton;



    }
}

