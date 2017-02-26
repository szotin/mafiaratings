namespace MafiaRatings
{
    partial class RegisterNewDialog
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
            System.Windows.Forms.Label label1;
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(RegisterNewDialog));
            System.Windows.Forms.Label label2;
            System.Windows.Forms.Label label3;
            System.Windows.Forms.Label label4;
            System.Windows.Forms.Button cancelButton;
            System.Windows.Forms.ImageList imageList;
            System.Windows.Forms.Label label8;
            this.nameTextBox = new System.Windows.Forms.TextBox();
            this.nickTextBox = new System.Windows.Forms.TextBox();
            this.emailTextBox = new System.Windows.Forms.TextBox();
            this.maleRadioButton = new System.Windows.Forms.RadioButton();
            this.femaleRadioButton = new System.Windows.Forms.RadioButton();
            this.okButton = new System.Windows.Forms.Button();
            this.onlineTestCheckBox = new System.Windows.Forms.CheckBox();
            this.toolTip = new System.Windows.Forms.ToolTip(this.components);
            this.statusLabel = new System.Windows.Forms.Label();
            this.durationComboBox = new System.Windows.Forms.ComboBox();
            label1 = new System.Windows.Forms.Label();
            label2 = new System.Windows.Forms.Label();
            label3 = new System.Windows.Forms.Label();
            label4 = new System.Windows.Forms.Label();
            cancelButton = new System.Windows.Forms.Button();
            imageList = new System.Windows.Forms.ImageList(this.components);
            label8 = new System.Windows.Forms.Label();
            this.SuspendLayout();
            // 
            // label1
            // 
            resources.ApplyResources(label1, "label1");
            label1.Name = "label1";
            // 
            // label2
            // 
            resources.ApplyResources(label2, "label2");
            label2.Name = "label2";
            // 
            // label3
            // 
            resources.ApplyResources(label3, "label3");
            label3.Name = "label3";
            // 
            // label4
            // 
            resources.ApplyResources(label4, "label4");
            label4.Name = "label4";
            // 
            // cancelButton
            // 
            resources.ApplyResources(cancelButton, "cancelButton");
            cancelButton.DialogResult = System.Windows.Forms.DialogResult.Cancel;
            cancelButton.Name = "cancelButton";
            cancelButton.UseVisualStyleBackColor = true;
            // 
            // imageList
            // 
            imageList.ImageStream = ((System.Windows.Forms.ImageListStreamer)(resources.GetObject("imageList.ImageStream")));
            imageList.TransparentColor = System.Drawing.Color.Transparent;
            imageList.Images.SetKeyName(0, "accept.png");
            imageList.Images.SetKeyName(1, "delete.png");
            // 
            // label8
            // 
            resources.ApplyResources(label8, "label8");
            label8.Name = "label8";
            // 
            // nameTextBox
            // 
            resources.ApplyResources(this.nameTextBox, "nameTextBox");
            this.nameTextBox.Name = "nameTextBox";
            this.nameTextBox.TextChanged += new System.EventHandler(this.nameTextBox_TextChanged);
            // 
            // nickTextBox
            // 
            resources.ApplyResources(this.nickTextBox, "nickTextBox");
            this.nickTextBox.Name = "nickTextBox";
            this.toolTip.SetToolTip(this.nickTextBox, resources.GetString("nickTextBox.ToolTip"));
            this.nickTextBox.TextChanged += new System.EventHandler(this.nickTextBox_TextChanged);
            // 
            // emailTextBox
            // 
            resources.ApplyResources(this.emailTextBox, "emailTextBox");
            this.emailTextBox.Name = "emailTextBox";
            this.emailTextBox.TextChanged += new System.EventHandler(this.RegisterNewDialog_Load);
            // 
            // maleRadioButton
            // 
            resources.ApplyResources(this.maleRadioButton, "maleRadioButton");
            this.maleRadioButton.Checked = true;
            this.maleRadioButton.Name = "maleRadioButton";
            this.maleRadioButton.TabStop = true;
            this.maleRadioButton.UseVisualStyleBackColor = true;
            // 
            // femaleRadioButton
            // 
            resources.ApplyResources(this.femaleRadioButton, "femaleRadioButton");
            this.femaleRadioButton.Name = "femaleRadioButton";
            this.femaleRadioButton.UseVisualStyleBackColor = true;
            // 
            // okButton
            // 
            resources.ApplyResources(this.okButton, "okButton");
            this.okButton.DialogResult = System.Windows.Forms.DialogResult.OK;
            this.okButton.Name = "okButton";
            this.okButton.UseVisualStyleBackColor = true;
            // 
            // onlineTestCheckBox
            // 
            resources.ApplyResources(this.onlineTestCheckBox, "onlineTestCheckBox");
            this.onlineTestCheckBox.ImageList = imageList;
            this.onlineTestCheckBox.Name = "onlineTestCheckBox";
            this.onlineTestCheckBox.TabStop = false;
            this.toolTip.SetToolTip(this.onlineTestCheckBox, resources.GetString("onlineTestCheckBox.ToolTip"));
            this.onlineTestCheckBox.UseVisualStyleBackColor = true;
            this.onlineTestCheckBox.CheckedChanged += new System.EventHandler(this.onlineTestCheckBox_CheckedChanged);
            // 
            // statusLabel
            // 
            resources.ApplyResources(this.statusLabel, "statusLabel");
            this.statusLabel.BorderStyle = System.Windows.Forms.BorderStyle.Fixed3D;
            this.statusLabel.Name = "statusLabel";
            // 
            // durationComboBox
            // 
            this.durationComboBox.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList;
            this.durationComboBox.FormattingEnabled = true;
            this.durationComboBox.Items.AddRange(new object[] {
            resources.GetString("durationComboBox.Items"),
            resources.GetString("durationComboBox.Items1"),
            resources.GetString("durationComboBox.Items2"),
            resources.GetString("durationComboBox.Items3"),
            resources.GetString("durationComboBox.Items4"),
            resources.GetString("durationComboBox.Items5"),
            resources.GetString("durationComboBox.Items6"),
            resources.GetString("durationComboBox.Items7"),
            resources.GetString("durationComboBox.Items8"),
            resources.GetString("durationComboBox.Items9")});
            resources.ApplyResources(this.durationComboBox, "durationComboBox");
            this.durationComboBox.Name = "durationComboBox";
            // 
            // RegisterNewDialog
            // 
            this.AcceptButton = this.okButton;
            resources.ApplyResources(this, "$this");
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.CancelButton = cancelButton;
            this.Controls.Add(this.durationComboBox);
            this.Controls.Add(label8);
            this.Controls.Add(this.statusLabel);
            this.Controls.Add(this.onlineTestCheckBox);
            this.Controls.Add(this.okButton);
            this.Controls.Add(cancelButton);
            this.Controls.Add(label4);
            this.Controls.Add(this.femaleRadioButton);
            this.Controls.Add(this.maleRadioButton);
            this.Controls.Add(this.emailTextBox);
            this.Controls.Add(label3);
            this.Controls.Add(this.nickTextBox);
            this.Controls.Add(label2);
            this.Controls.Add(this.nameTextBox);
            this.Controls.Add(label1);
            this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = false;
            this.Name = "RegisterNewDialog";
            this.ShowInTaskbar = false;
            this.Load += new System.EventHandler(this.RegisterNewDialog_Load);
            this.ResumeLayout(false);
            this.PerformLayout();

        }

        #endregion

        private System.Windows.Forms.TextBox nameTextBox;
        private System.Windows.Forms.TextBox nickTextBox;
        private System.Windows.Forms.TextBox emailTextBox;
        private System.Windows.Forms.RadioButton maleRadioButton;
        private System.Windows.Forms.RadioButton femaleRadioButton;
        private System.Windows.Forms.Button okButton;
        private System.Windows.Forms.CheckBox onlineTestCheckBox;
        private System.Windows.Forms.ToolTip toolTip;
        private System.Windows.Forms.Label statusLabel;
        private System.Windows.Forms.ComboBox durationComboBox;

    }
}