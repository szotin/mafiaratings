namespace MafiaRatings
{
    partial class GameButtons
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

        #region Component Designer generated code

        /// <summary> 
        /// Required method for Designer support - do not modify 
        /// the contents of this method with the code editor.
        /// </summary>
        private void InitializeComponent()
        {
            this.components = new System.ComponentModel.Container();
            System.Windows.Forms.ImageList imageList;
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(GameButtons));
            System.Windows.Forms.Panel panel2;
            this.warningsLabel = new System.Windows.Forms.Label();
            this.toolTip = new System.Windows.Forms.ToolTip(this.components);
            this.panel1 = new System.Windows.Forms.Panel();
            this.warningButton = new System.Windows.Forms.Button();
            this.suisideButton = new System.Windows.Forms.Button();
            this.kickOutButton = new System.Windows.Forms.Button();
            imageList = new System.Windows.Forms.ImageList(this.components);
            panel2 = new System.Windows.Forms.Panel();
            panel2.SuspendLayout();
            this.panel1.SuspendLayout();
            this.SuspendLayout();
            // 
            // imageList
            // 
            imageList.ImageStream = ((System.Windows.Forms.ImageListStreamer)(resources.GetObject("imageList.ImageStream")));
            imageList.TransparentColor = System.Drawing.Color.Magenta;
            imageList.Images.SetKeyName(0, "sheriff.png");
            imageList.Images.SetKeyName(1, "not_sheriff.png");
            // 
            // panel2
            // 
            panel2.BorderStyle = System.Windows.Forms.BorderStyle.Fixed3D;
            panel2.Controls.Add(this.warningsLabel);
            panel2.Dock = System.Windows.Forms.DockStyle.Fill;
            panel2.Location = new System.Drawing.Point(0, 0);
            panel2.Name = "panel2";
            panel2.Size = new System.Drawing.Size(208, 28);
            panel2.TabIndex = 6;
            // 
            // warningsLabel
            // 
            this.warningsLabel.Dock = System.Windows.Forms.DockStyle.Fill;
            this.warningsLabel.Location = new System.Drawing.Point(0, 0);
            this.warningsLabel.Name = "warningsLabel";
            this.warningsLabel.Size = new System.Drawing.Size(204, 24);
            this.warningsLabel.TabIndex = 0;
            this.warningsLabel.Text = "3 warnings";
            this.warningsLabel.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            // 
            // panel1
            // 
            this.panel1.BorderStyle = System.Windows.Forms.BorderStyle.Fixed3D;
            this.panel1.Controls.Add(this.warningButton);
            this.panel1.Controls.Add(this.suisideButton);
            this.panel1.Controls.Add(this.kickOutButton);
            this.panel1.Dock = System.Windows.Forms.DockStyle.Right;
            this.panel1.Location = new System.Drawing.Point(208, 0);
            this.panel1.Name = "panel1";
            this.panel1.Size = new System.Drawing.Size(69, 28);
            this.panel1.TabIndex = 5;
            // 
            // warningButton
            // 
            this.warningButton.BackColor = System.Drawing.Color.Transparent;
            this.warningButton.Dock = System.Windows.Forms.DockStyle.Right;
            this.warningButton.FlatAppearance.BorderSize = 0;
            this.warningButton.FlatStyle = System.Windows.Forms.FlatStyle.Flat;
            this.warningButton.Image = ((System.Drawing.Image)(resources.GetObject("warningButton.Image")));
            this.warningButton.Location = new System.Drawing.Point(-1, 0);
            this.warningButton.Name = "warningButton";
            this.warningButton.Size = new System.Drawing.Size(22, 24);
            this.warningButton.TabIndex = 7;
            this.warningButton.UseVisualStyleBackColor = false;
            this.warningButton.Click += new System.EventHandler(this.warningButton_Click);
            // 
            // suisideButton
            // 
            this.suisideButton.BackColor = System.Drawing.Color.Transparent;
            this.suisideButton.Dock = System.Windows.Forms.DockStyle.Right;
            this.suisideButton.FlatAppearance.BorderSize = 0;
            this.suisideButton.FlatStyle = System.Windows.Forms.FlatStyle.Flat;
            this.suisideButton.Image = ((System.Drawing.Image)(resources.GetObject("suisideButton.Image")));
            this.suisideButton.Location = new System.Drawing.Point(21, 0);
            this.suisideButton.Name = "suisideButton";
            this.suisideButton.Size = new System.Drawing.Size(22, 24);
            this.suisideButton.TabIndex = 5;
            this.suisideButton.UseVisualStyleBackColor = false;
            this.suisideButton.Click += new System.EventHandler(this.suisideButton_Click);
            // 
            // kickOutButton
            // 
            this.kickOutButton.BackColor = System.Drawing.Color.Transparent;
            this.kickOutButton.Dock = System.Windows.Forms.DockStyle.Right;
            this.kickOutButton.FlatAppearance.BorderSize = 0;
            this.kickOutButton.FlatStyle = System.Windows.Forms.FlatStyle.Flat;
            this.kickOutButton.Image = ((System.Drawing.Image)(resources.GetObject("kickOutButton.Image")));
            this.kickOutButton.Location = new System.Drawing.Point(43, 0);
            this.kickOutButton.Name = "kickOutButton";
            this.kickOutButton.Size = new System.Drawing.Size(22, 24);
            this.kickOutButton.TabIndex = 6;
            this.kickOutButton.UseVisualStyleBackColor = false;
            this.kickOutButton.Click += new System.EventHandler(this.kickOutButton_Click);
            // 
            // GameButtons
            // 
            this.AutoScaleDimensions = new System.Drawing.SizeF(6F, 13F);
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.Controls.Add(panel2);
            this.Controls.Add(this.panel1);
            this.Name = "GameButtons";
            this.Size = new System.Drawing.Size(277, 28);
            this.EnabledChanged += new System.EventHandler(this.GameButtons_EnabledChanged);
            panel2.ResumeLayout(false);
            this.panel1.ResumeLayout(false);
            this.ResumeLayout(false);

        }

        #endregion

        private System.Windows.Forms.ToolTip toolTip;
        private System.Windows.Forms.Panel panel1;
        private System.Windows.Forms.Button warningButton;
        private System.Windows.Forms.Button suisideButton;
        private System.Windows.Forms.Button kickOutButton;
        private System.Windows.Forms.Label warningsLabel;

    }
}
