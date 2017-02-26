namespace MafiaRatings
{
    partial class RegisterControl
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
            System.Windows.Forms.Label label1;
            System.ComponentModel.ComponentResourceManager resources = new System.ComponentModel.ComponentResourceManager(typeof(RegisterControl));
            System.Windows.Forms.Label label2;
            System.Windows.Forms.Label label3;
            this.userNameLabel = new System.Windows.Forms.Label();
            this.nickComboBox = new System.Windows.Forms.ComboBox();
            this.durationComboBox = new System.Windows.Forms.ComboBox();
            this.passwordTextBox = new System.Windows.Forms.TextBox();
            this.joinClubCheckBox = new System.Windows.Forms.CheckBox();
            label1 = new System.Windows.Forms.Label();
            label2 = new System.Windows.Forms.Label();
            label3 = new System.Windows.Forms.Label();
            this.SuspendLayout();
            // 
            // label1
            // 
            resources.ApplyResources(label1, "label1");
            label1.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            label1.Name = "label1";
            // 
            // label2
            // 
            resources.ApplyResources(label2, "label2");
            label2.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            label2.Name = "label2";
            // 
            // label3
            // 
            resources.ApplyResources(label3, "label3");
            label3.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            label3.Name = "label3";
            // 
            // userNameLabel
            // 
            resources.ApplyResources(this.userNameLabel, "userNameLabel");
            this.userNameLabel.BorderStyle = System.Windows.Forms.BorderStyle.Fixed3D;
            this.userNameLabel.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            this.userNameLabel.Name = "userNameLabel";
            // 
            // nickComboBox
            // 
            resources.ApplyResources(this.nickComboBox, "nickComboBox");
            this.nickComboBox.AutoCompleteMode = System.Windows.Forms.AutoCompleteMode.Suggest;
            this.nickComboBox.AutoCompleteSource = System.Windows.Forms.AutoCompleteSource.ListItems;
            this.nickComboBox.FormattingEnabled = true;
            this.nickComboBox.Name = "nickComboBox";
            // 
            // durationComboBox
            // 
            resources.ApplyResources(this.durationComboBox, "durationComboBox");
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
            this.durationComboBox.Name = "durationComboBox";
            // 
            // passwordTextBox
            // 
            resources.ApplyResources(this.passwordTextBox, "passwordTextBox");
            this.passwordTextBox.Name = "passwordTextBox";
            this.passwordTextBox.UseSystemPasswordChar = true;
            // 
            // joinClubCheckBox
            // 
            resources.ApplyResources(this.joinClubCheckBox, "joinClubCheckBox");
            this.joinClubCheckBox.ImageKey = global::MafiaRatings.Properties.Resources.String1;
            this.joinClubCheckBox.Name = "joinClubCheckBox";
            this.joinClubCheckBox.UseVisualStyleBackColor = true;
            // 
            // RegisterControl
            // 
            resources.ApplyResources(this, "$this");
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.Controls.Add(this.joinClubCheckBox);
            this.Controls.Add(this.passwordTextBox);
            this.Controls.Add(this.durationComboBox);
            this.Controls.Add(this.nickComboBox);
            this.Controls.Add(label3);
            this.Controls.Add(label2);
            this.Controls.Add(label1);
            this.Controls.Add(this.userNameLabel);
            this.Name = "RegisterControl";
            this.ResumeLayout(false);
            this.PerformLayout();

        }

        #endregion

        private System.Windows.Forms.Label userNameLabel;
        private System.Windows.Forms.ComboBox nickComboBox;
        private System.Windows.Forms.ComboBox durationComboBox;
        private System.Windows.Forms.TextBox passwordTextBox;
        private System.Windows.Forms.CheckBox joinClubCheckBox;
    }
}
