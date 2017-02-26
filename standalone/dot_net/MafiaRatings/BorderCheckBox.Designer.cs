namespace MafiaRatings
{
    partial class BorderCheckBox
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
            this.checkBox = new System.Windows.Forms.CheckBox();
            this.SuspendLayout();
            // 
            // checkBox
            // 
            this.checkBox.Anchor = ((System.Windows.Forms.AnchorStyles)((System.Windows.Forms.AnchorStyles.Left | System.Windows.Forms.AnchorStyles.Right)));
            this.checkBox.Location = new System.Drawing.Point(3, 8);
            this.checkBox.Name = "checkBox";
            this.checkBox.Size = new System.Drawing.Size(119, 24);
            this.checkBox.TabIndex = 0;
            this.checkBox.Text = "checkBox1";
            this.checkBox.TextAlign = System.Drawing.ContentAlignment.MiddleCenter;
            this.checkBox.UseVisualStyleBackColor = true;
            // 
            // BorderCheckBox
            // 
            this.AutoScaleDimensions = new System.Drawing.SizeF(6F, 13F);
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.BorderStyle = System.Windows.Forms.BorderStyle.Fixed3D;
            this.Controls.Add(this.checkBox);
            this.Name = "BorderCheckBox";
            this.Size = new System.Drawing.Size(125, 45);
            this.EnabledChanged += new System.EventHandler(this.BorderCheckBox_EnabledChanged_1);
            this.Resize += new System.EventHandler(this.BorderCheckBox_Resize);
            this.ResumeLayout(false);

        }

        #endregion

        private System.Windows.Forms.CheckBox checkBox;
    }
}
