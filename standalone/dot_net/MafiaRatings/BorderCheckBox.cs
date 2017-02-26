using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Drawing;
using System.Data;
using System.Linq;
using System.Text;
using System.Windows.Forms;

namespace MafiaRatings
{
    public partial class BorderCheckBox : UserControl
    {
        public BorderCheckBox()
        {
            InitializeComponent();
        }

        public string String
        {
            get { return checkBox.Text; }
            set { checkBox.Text = value; }
        }

        public bool Checked
        {
            get { return checkBox.Checked; }
            set { checkBox.Checked = value; }
        }

        private void BorderCheckBox_EnabledChanged(object sender, EventArgs e)
        {
            checkBox.Visible = Enabled;
        }

        public event EventHandler CheckedChanged
        {
            add { checkBox.CheckedChanged += value; }
            remove { checkBox.CheckedChanged -= value; }
        }

        public bool IsMyEvent(object sender)
        {
            return checkBox == sender;
        }

        private void BorderCheckBox_Resize(object sender, EventArgs e)
        {
            checkBox.Top = (ClientRectangle.Height - checkBox.Height) / 2;
        }

        private void BorderCheckBox_EnabledChanged_1(object sender, EventArgs e)
        {
            checkBox.Visible = Enabled;
        }
    }
}
