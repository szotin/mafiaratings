using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Windows.Forms;
using MafiaRatings.GameObjects;

namespace MafiaRatings
{
    public partial class LastServerResponseDialog : Form
    {
        public LastServerResponseDialog(Gate gate)
        {
            InitializeComponent();
            if (gate.LastServerResponse != null)
            {
                webBrowser.DocumentText = gate.LastServerResponse;
            }
        }
    }
}
