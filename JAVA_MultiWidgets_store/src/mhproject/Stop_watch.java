/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package mhproject;

import java.awt.*;
import java.awt.event.*;
import javax.swing.*;

public class Stop_watch extends JFrame implements ActionListener {
    private JLabel timeLabel;
    private JButton startButton, stopButton, resetButton;
    private Timer timer;
    private int elapsedTime;

    public Stop_watch() {
        // Set up the user interface
        timeLabel = new JLabel("00:00:00", JLabel.CENTER);
        timeLabel.setFont(new Font("Arial", Font.PLAIN, 50));
        timeLabel.setForeground(Color.red);

        startButton = new JButton("Start");
        startButton.addActionListener(this);
        stopButton = new JButton("Stop");
        stopButton.addActionListener(this);
        resetButton = new JButton("Reset");
        resetButton.addActionListener(this);

        JPanel buttonPanel = new JPanel();
        buttonPanel.setLayout(new GridLayout(1, 3));
        buttonPanel.add(startButton);
        buttonPanel.add(stopButton);
        buttonPanel.add(resetButton);

        Container contentPane = getContentPane();
        contentPane.setLayout(new BorderLayout());
        contentPane.add(timeLabel, BorderLayout.CENTER);
        contentPane.add(buttonPanel, BorderLayout.SOUTH);

        // Set up the timer
        timer = new Timer(1000, (ActionEvent e) -> {
            elapsedTime += 1000;
            updateTimeLabel();
        });
    }

    @Override
    public void actionPerformed(ActionEvent e) {
        if (e.getSource() == startButton) {
            timer.start();
        } else if (e.getSource() == stopButton) {
            timer.stop();
        } else if (e.getSource() == resetButton) {
            timer.stop();
            elapsedTime = 0;
            updateTimeLabel();
        }
    }

    private void updateTimeLabel() {
        int hours = elapsedTime / 3600000;
        int minutes = (elapsedTime % 3600000) / 60000;
        int seconds = (elapsedTime % 60000) / 1000;
        String time = String.format("%02d:%02d:%02d", hours, minutes, seconds);
        timeLabel.setText(time);
    }

    
}
