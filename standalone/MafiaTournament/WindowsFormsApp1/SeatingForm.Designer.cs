namespace TournamentSeating
{
    partial class SeatingForm
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
            System.Windows.Forms.Label label3;
            System.Windows.Forms.Label label2;
            System.Windows.Forms.Label label1;
            System.Windows.Forms.Panel panel1;
            System.Windows.Forms.DataGridViewCellStyle dataGridViewCellStyle2 = new System.Windows.Forms.DataGridViewCellStyle();
            this.calculateButton = new System.Windows.Forms.Button();
            this.tablesUpDown = new System.Windows.Forms.NumericUpDown();
            this.gamesUpDown = new System.Windows.Forms.NumericUpDown();
            this.playersUpDown = new System.Windows.Forms.NumericUpDown();
            this.grid = new System.Windows.Forms.DataGridView();
            label3 = new System.Windows.Forms.Label();
            label2 = new System.Windows.Forms.Label();
            label1 = new System.Windows.Forms.Label();
            panel1 = new System.Windows.Forms.Panel();
            panel1.SuspendLayout();
            ((System.ComponentModel.ISupportInitialize)(this.tablesUpDown)).BeginInit();
            ((System.ComponentModel.ISupportInitialize)(this.gamesUpDown)).BeginInit();
            ((System.ComponentModel.ISupportInitialize)(this.playersUpDown)).BeginInit();
            ((System.ComponentModel.ISupportInitialize)(this.grid)).BeginInit();
            this.SuspendLayout();
            // 
            // label3
            // 
            label3.AutoSize = true;
            label3.Location = new System.Drawing.Point(406, 19);
            label3.Name = "label3";
            label3.Size = new System.Drawing.Size(90, 13);
            label3.TabIndex = 4;
            label3.Text = "Number of tables:";
            // 
            // label2
            // 
            label2.AutoSize = true;
            label2.Location = new System.Drawing.Point(251, 19);
            label2.Name = "label2";
            label2.Size = new System.Drawing.Size(92, 13);
            label2.TabIndex = 2;
            label2.Text = "Games per player:";
            // 
            // label1
            // 
            label1.AutoSize = true;
            label1.Location = new System.Drawing.Point(99, 19);
            label1.Name = "label1";
            label1.Size = new System.Drawing.Size(95, 13);
            label1.TabIndex = 0;
            label1.Text = "Number of players:";
            // 
            // panel1
            // 
            panel1.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle;
            panel1.Controls.Add(this.calculateButton);
            panel1.Controls.Add(this.tablesUpDown);
            panel1.Controls.Add(label3);
            panel1.Controls.Add(this.gamesUpDown);
            panel1.Controls.Add(label2);
            panel1.Controls.Add(this.playersUpDown);
            panel1.Controls.Add(label1);
            panel1.Dock = System.Windows.Forms.DockStyle.Bottom;
            panel1.Location = new System.Drawing.Point(0, 430);
            panel1.Name = "panel1";
            panel1.Size = new System.Drawing.Size(1147, 48);
            panel1.TabIndex = 0;
            // 
            // calculateButton
            // 
            this.calculateButton.Location = new System.Drawing.Point(12, 14);
            this.calculateButton.Name = "calculateButton";
            this.calculateButton.Size = new System.Drawing.Size(75, 23);
            this.calculateButton.TabIndex = 6;
            this.calculateButton.Text = "Calculate";
            this.calculateButton.UseVisualStyleBackColor = true;
            this.calculateButton.Click += new System.EventHandler(this.calculateButton_Click);
            // 
            // tablesUpDown
            // 
            this.tablesUpDown.Location = new System.Drawing.Point(507, 17);
            this.tablesUpDown.Minimum = new decimal(new int[] {
            1,
            0,
            0,
            0});
            this.tablesUpDown.Name = "tablesUpDown";
            this.tablesUpDown.Size = new System.Drawing.Size(45, 20);
            this.tablesUpDown.TabIndex = 5;
            this.tablesUpDown.Value = new decimal(new int[] {
            1,
            0,
            0,
            0});
            this.tablesUpDown.ValueChanged += new System.EventHandler(this.tablesUpDown_ValueChanged);
            // 
            // gamesUpDown
            // 
            this.gamesUpDown.Location = new System.Drawing.Point(352, 17);
            this.gamesUpDown.Minimum = new decimal(new int[] {
            1,
            0,
            0,
            0});
            this.gamesUpDown.Name = "gamesUpDown";
            this.gamesUpDown.Size = new System.Drawing.Size(45, 20);
            this.gamesUpDown.TabIndex = 3;
            this.gamesUpDown.Value = new decimal(new int[] {
            5,
            0,
            0,
            0});
            this.gamesUpDown.ValueChanged += new System.EventHandler(this.gamesUpDown_ValueChanged);
            // 
            // playersUpDown
            // 
            this.playersUpDown.Location = new System.Drawing.Point(200, 17);
            this.playersUpDown.Maximum = new decimal(new int[] {
            1000,
            0,
            0,
            0});
            this.playersUpDown.Minimum = new decimal(new int[] {
            2,
            0,
            0,
            0});
            this.playersUpDown.Name = "playersUpDown";
            this.playersUpDown.Size = new System.Drawing.Size(45, 20);
            this.playersUpDown.TabIndex = 1;
            this.playersUpDown.Value = new decimal(new int[] {
            10,
            0,
            0,
            0});
            this.playersUpDown.ValueChanged += new System.EventHandler(this.playersUpDown_ValueChanged);
            // 
            // grid
            // 
            this.grid.AllowUserToAddRows = false;
            this.grid.BackgroundColor = System.Drawing.SystemColors.Control;
            dataGridViewCellStyle2.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft;
            dataGridViewCellStyle2.BackColor = System.Drawing.SystemColors.ControlDark;
            dataGridViewCellStyle2.Font = new System.Drawing.Font("Microsoft Sans Serif", 8.25F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((byte)(0)));
            dataGridViewCellStyle2.ForeColor = System.Drawing.SystemColors.WindowText;
            dataGridViewCellStyle2.SelectionBackColor = System.Drawing.SystemColors.Highlight;
            dataGridViewCellStyle2.SelectionForeColor = System.Drawing.SystemColors.HighlightText;
            dataGridViewCellStyle2.WrapMode = System.Windows.Forms.DataGridViewTriState.True;
            this.grid.ColumnHeadersDefaultCellStyle = dataGridViewCellStyle2;
            this.grid.ColumnHeadersHeightSizeMode = System.Windows.Forms.DataGridViewColumnHeadersHeightSizeMode.AutoSize;
            this.grid.Dock = System.Windows.Forms.DockStyle.Fill;
            this.grid.Location = new System.Drawing.Point(0, 0);
            this.grid.Name = "grid";
            this.grid.ReadOnly = true;
            this.grid.ShowEditingIcon = false;
            this.grid.Size = new System.Drawing.Size(1147, 430);
            this.grid.TabIndex = 1;
            // 
            // SeatingForm
            // 
            this.AutoScaleDimensions = new System.Drawing.SizeF(6F, 13F);
            this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font;
            this.ClientSize = new System.Drawing.Size(1147, 478);
            this.Controls.Add(this.grid);
            this.Controls.Add(panel1);
            this.Name = "SeatingForm";
            this.Text = "Tournamnent seating arrangement";
            panel1.ResumeLayout(false);
            panel1.PerformLayout();
            ((System.ComponentModel.ISupportInitialize)(this.tablesUpDown)).EndInit();
            ((System.ComponentModel.ISupportInitialize)(this.gamesUpDown)).EndInit();
            ((System.ComponentModel.ISupportInitialize)(this.playersUpDown)).EndInit();
            ((System.ComponentModel.ISupportInitialize)(this.grid)).EndInit();
            this.ResumeLayout(false);

        }

        #endregion
        private System.Windows.Forms.NumericUpDown tablesUpDown;
        private System.Windows.Forms.NumericUpDown gamesUpDown;
        private System.Windows.Forms.NumericUpDown playersUpDown;
        private System.Windows.Forms.DataGridView grid;
        private System.Windows.Forms.Button calculateButton;
    }
}

